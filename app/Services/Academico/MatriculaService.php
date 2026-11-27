<?php

namespace App\Services\Academico;

use App\Models\Academico\Curso;
use App\Models\Academico\MatriculaGrupo;
use App\Models\Academico\Programa;
use App\Models\Finanzas\Matricula;
use App\Models\Finanzas\Pago;
use App\Models\User;
use App\Services\Finanzas\PagoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de negocio de la matrícula (panel Admin › Matrículas): matricular en un grupo,
 * cambiar de grupo y cambiar de estado (aplazar, retirar, graduar, reintegrar).
 * - Una matrícula vigente (activa o aplazada) por estudiante.
 * - Solo las activas ocupan cupo; el cupo se revisa dentro de la transacción (bloqueando el grupo).
 * - Aplazar o retirarse anula las cuotas futuras; reintegrarse vuelve a generarlas.
 * - Nadie empieza una carrera en un grupo que ya empezó; quien vuelve o se cambia de grupo solo entra a uno que no le
 *   haga perder módulos (noAlcanza(), decidido con el usuario el 26-11-2026).
 * Las validaciones de entrada viven en los Form Requests de Admin/Matriculas.
 */
class MatriculaService
{
    public function __construct(private PagoService $pagos)
    {
    }

    /**
     * Matricula al estudiante en el grupo y le cobra el derecho de matrícula (vence al empezar
     * las clases). Si $pagoMatricula trae datos, lo registra pagado de una vez.
     * El plan de pago (mensual o por periodo) lo elige después el estudiante desde su panel.
     */
    public function matricular(User $estudiante, Curso $grupo, ?array $pagoMatricula): Matricula
    {
        return DB::transaction(function () use ($estudiante, $grupo, $pagoMatricula) {
            // Dos clics o dos personas a la vez: el segundo espera y ve la matrícula que creó el primero
            $this->bloquearEstudiante($estudiante->id);
            $this->asegurarSinVigente($estudiante->id);
            $this->asegurarCupo($grupo);
            $this->asegurarAlcance($grupo);

            $matricula = Matricula::create([
                'codigo'        => Matricula::siguienteCodigo(),
                'estudiante_id' => $estudiante->id,
                'programa_id'   => $grupo->programa_id,
                'precio_programa' => Programa::whereKey($grupo->programa_id)->value('precio_total'),   // el precio de hoy queda pactado
                'curso_id'      => $grupo->id,
                'sede_id'       => $grupo->sede_id,
                'estado'        => 'activo',
                'estado_fecha'  => today(),
                'fecha_inicio'  => today(),
            ]);
            $this->abrirPaso($matricula, $grupo->id, today());
            $estudiante->cursosComoEstudiante()->syncWithoutDetaching([$grupo->id]);
            // Vuelve a estudiar: si su cuenta se desactivó al irse, entra otra vez a su panel
            if (! $estudiante->activo) {
                $estudiante->update(['activo' => true]);
            }

            $cobro = $this->pagos->cobrarMatricula($matricula, $this->pagos->vencimientoDerecho($grupo));
            if ($cobro && $pagoMatricula) {
                $this->pagos->registrarPago($matricula, (float) $cobro->monto_total, $pagoMatricula);
            }

            return $matricula;
        });
    }

    /** Pasa la matrícula activa a otro grupo del mismo programa (la lista de grupos se actualiza). */
    public function cambiarGrupo(Matricula $matricula, Curso $nuevo): void
    {
        DB::transaction(function () use ($matricula, $nuevo) {
            $this->asegurarCupo($nuevo);
            $this->asegurarAlcance($nuevo, $matricula, true);
            // El historial queda: en el grupo viejo cuenta hasta hoy y en el nuevo desde hoy
            $this->asegurarHistorial($matricula);
            $this->cerrarPaso($matricula, today());
            $this->moverDeGrupo($matricula, $nuevo);
            $this->abrirPaso($matricula, $nuevo->id, today());
            $m = $matricula->fresh(['curso.programa', 'programa']);
            $this->pagos->reprogramarDerecho($m);
            // Las cuotas que debe cuentan desde el inicio de clases del grupo nuevo
            $this->pagos->reprogramarCuotas($m);
        });
    }

    /**
     * aplazado / retirado: libera el cupo y anula las cuotas que vencen después de $fecha. El derecho
     *   de matrícula sin pagar que aún no vencía queda en espera (aplazado) o se anula (retirado).
     * graduado: termina el programa (el Request exige grupo finalizado, todos los módulos aprobados,
     *   plan pagado y paz y salvo).
     * activo (reintegro desde aplazado): vuelve a $grupo (con cupo), el derecho en espera vence al
     *   iniciar ese grupo y se generan las cuotas que faltan (desde el reintegro, o desde que empieza ese grupo si es
     *   después).
     * anulado (se hizo por error, sin pagos): sale de la lista del grupo y no se cobra nada.
     */
    public function cambiarEstado(Matricula $matricula, string $estado, CarbonInterface $fecha, ?string $motivo, ?Curso $grupo = null): void
    {
        DB::transaction(function () use ($matricula, $estado, $fecha, $motivo, $grupo) {
            // Se revisa otra vez con la matrícula bloqueada: otra persona pudo cambiarla o pagarle entre tanto
            $matricula = $this->bloquearMatricula($matricula);
            if (! in_array($estado, Matricula::TRANSICIONES[$matricula->estado] ?? [], true)) {
                throw ValidationException::withMessages(['estado' => "La matrícula ya quedó {$matricula->estado}: otra persona la acaba de cambiar. Recarga la página."]);
            }
            if ($estado === 'anulado' && ($matricula->recaudos()->whereNull('anulado_en')->exists() || $this->pagos->dineroEn($matricula) > 0)) {
                throw ValidationException::withMessages(['estado' => 'Esta matrícula tiene dinero (pagos o saldo trasladado): ya no se puede anular. Si el estudiante se va, usa Retirar.']);
            }
            if ($estado === 'graduado' && ($debe = $this->pagos->resumen($matricula->load('pagos', 'recaudos', 'programa'))['por_pagar']) > 0) {
                throw ValidationException::withMessages(['estado' => 'Para graduarse debe estar a paz y salvo: todavía debe $ '.number_format($debe, 0, ',', '.').'.']);
            }
            // Historial de grupos (27-11-2026): antes de cambiar nada queda el paso que llevaba hasta ahora
            $this->asegurarHistorial($matricula);
            if ($estado === 'activo') {
                $grupo ??= $matricula->curso;
                $this->asegurarCupo($grupo);
                $this->asegurarAlcance($grupo, $matricula);
                if ($grupo->id !== $matricula->curso_id) {
                    $this->moverDeGrupo($matricula, $grupo);
                }
                // Reintegro: vuelve a contar desde ese día (en el mismo grupo o en otro), no en las clases mientras estuvo aplazado
                $this->abrirPaso($matricula, $grupo->id, Carbon::parse($fecha));
            } else {
                $this->cerrarPaso($matricula, Carbon::parse($fecha));
            }

            $matricula->update(['estado' => $estado, 'estado_fecha' => $fecha, 'estado_motivo' => $motivo]);

            if (in_array($estado, ['aplazado', 'retirado'], true)) {
                $this->pagos->anularCuotasFuturas($matricula, $fecha);
            }
            // Derecho de matrícula sin pagar que aún no vencía: en espera si se aplaza, anulado si se retira
            if ($estado === 'aplazado') {
                $this->pagos->derechoEnEspera($matricula, $fecha);
            }
            if ($estado === 'retirado') {
                $this->pagos->anularDerechoSinCausar($matricula, $fecha);
            }
            if ($estado === 'anulado') {
                $this->pagos->anularCobrosPendientes($matricula);
                $this->sacarDeLista($matricula, $matricula->curso_id);
            }
            if ($estado === 'activo') {
                $this->pagos->programarDerecho($matricula, $matricula->fresh()->curso);
                // Lo que había abonado a cuotas que se anularon al aplazarse vuelve como saldo a favor
                $this->pagos->recuperarAdelantado($matricula->fresh());
                // La siguiente cuota vence el día 10: si ya pasó este mes, empieza el próximo; y nunca antes de que empiecen
                // las clases del grupo al que vuelve
                $desde = Carbon::parse($fecha);
                if ($desde->day > Pago::DIA_VENCIMIENTO) {
                    $desde->addMonthNoOverflow();
                }
                $primera = $this->pagos->primeraCuota($matricula->fresh()->curso);
                if ($primera->gt($desde)) {
                    $desde = Carbon::parse($primera);
                }
                $this->pagos->generarCuotas($matricula->fresh(), $desde);
            }
        });
    }

    /**
     * Cambio de programa en un solo paso: la matrícula vigente queda retirada ("Cambio de programa a…")
     * y se crea una nueva en $grupo que la referencia (origen_id). El derecho de matrícula no se vuelve
     * a cobrar si ya lo pagó; si no, se anula el viejo y se cobra el del programa nuevo. Lo adelantado
     * (después de cubrir lo vencido de la vieja) pasa como abono a la nueva. Devuelve la nueva.
     */
    public function cambiarPrograma(Matricula $vieja, Curso $grupo, string $motivo): Matricula
    {
        return DB::transaction(function () use ($vieja, $grupo, $motivo) {
            $this->bloquearEstudiante($vieja->estudiante_id);
            $vieja = $this->bloquearMatricula($vieja);
            if (! in_array($vieja->estado, Matricula::VIGENTES, true)) {
                throw ValidationException::withMessages(['grupo_id' => "Esta matrícula ya quedó {$vieja->estado}: otra persona la acaba de cambiar. Recarga la página."]);
            }
            $this->asegurarSinVigente($vieja->estudiante_id, $vieja->id);
            $this->asegurarCupo($grupo);
            $this->asegurarAlcance($grupo);   // una carrera nueva se empieza desde el principio
            $hoy = today();

            $vieja->update([
                'estado'        => 'retirado',
                'estado_fecha'  => $hoy,
                'estado_motivo' => "Cambio de carrera a {$grupo->programa->nombre}: {$motivo}",
            ]);
            $this->asegurarHistorial($vieja);
            $this->cerrarPaso($vieja, $hoy);
            $this->pagos->anularCuotasFuturas($vieja, $hoy);
            $derechoPagado = $vieja->pagos()->where('tipo', 'matricula')->where('estado', 'pagado')->exists();
            $vieja->pagos()->where('tipo', 'matricula')->whereIn('estado', Pago::POR_PAGAR)->update(['estado' => 'anulado']);
            $this->sacarDeLista($vieja, $vieja->curso_id);

            $nueva = Matricula::create([
                'codigo'        => Matricula::siguienteCodigo(),
                'estudiante_id' => $vieja->estudiante_id,
                'programa_id'   => $grupo->programa_id,
                'precio_programa' => Programa::whereKey($grupo->programa_id)->value('precio_total'),
                'curso_id'      => $grupo->id,
                'sede_id'       => $grupo->sede_id,
                'origen_id'     => $vieja->id,
                'estado'        => 'activo',
                'estado_fecha'  => $hoy,
                'fecha_inicio'  => $hoy,
            ]);
            $this->abrirPaso($nueva, $grupo->id, $hoy);
            $vieja->estudiante->cursosComoEstudiante()->syncWithoutDetaching([$grupo->id]);

            if (! $derechoPagado) {
                $this->pagos->cobrarMatricula($nueva, $this->pagos->vencimientoDerecho($grupo));
            }
            $this->pagos->trasladarAdelantado($vieja->fresh(), $nueva->fresh());

            return $nueva->fresh();
        });
    }

    /**
     * Por qué no puede entrar a $grupo, o null si puede (decidido con el usuario el 26-11-2026):
     * - Matrícula nueva o cambio de carrera ($matricula null): solo un grupo que todavía no empieza (o empieza hoy).
     * - Reintegro: un grupo en curso sirve si todavía no empezó el primer módulo que le falta (lo aprobado se conserva:
     *   la nota sigue a la matrícula). Si ya lo empezó, perdería clases de ese módulo y no podría graduarse.
     * - Cambio de grupo ($cambioDeGrupo): el grupo nuevo no puede haber empezado ese módulo antes que su grupo actual (dos
     *   grupos al mismo ritmo sí se pueden cambiar).
     */
    public function noAlcanza(Curso $grupo, ?Matricula $matricula = null, bool $cambioDeGrupo = false): ?string
    {
        $hoy = today();
        if (! $grupo->fecha_inicio || $grupo->fecha_inicio->gte($hoy)) {
            return null;
        }
        $nombre = "El {$grupo->nombre} ({$grupo->codigo})";
        $desde = $grupo->fecha_inicio->locale('es')->translatedFormat('j \d\e F');
        if (! $matricula) {
            return "{$nombre} ya empezó clases el {$desde}: nadie empieza la carrera en un grupo en curso. Elige uno que todavía no haya empezado.";
        }
        $matricula->loadMissing(['programa.modulos', 'calificaciones']);
        $aprobados = $matricula->calificaciones->filter->aprobada()->pluck('programa_modulo_id')->flip();
        $falta = $matricula->programa->modulos->first(fn ($m) => ! $aprobados->has($m->id));
        if (! $falta) {
            return null;
        }
        $calificaciones = app(CalificacionService::class);
        $inicioEn = function (Curso $c) use ($calificaciones, $falta) {
            $c->loadMissing(['programa.modulos', 'historialSesiones']);

            return collect($calificaciones->fechasModulos($c))->firstWhere('id', $falta->id)['ini'] ?? null;
        };
        $ini = $inicioEn($grupo);
        if (! $ini) {
            return "{$nombre} ya empezó y no tiene horario: no se sabe en qué módulo va. Elige otro grupo o pídele que arme su horario.";
        }
        // Hasta dónde puede ir adelantado el grupo nuevo: hoy (reintegro) o lo que ya avanzó su grupo actual (cambio de grupo)
        $limite = $hoy->toDateString();
        if ($cambioDeGrupo && $matricula->curso && ($actual = $inicioEn($matricula->curso)) && $actual < $limite) {
            $limite = $actual;
        }
        if ($ini < $limite) {
            return "{$nombre} ya empezó el módulo «{$falta->nombre}», que todavía le falta: perdería clases y no podría graduarse. Elige un grupo que no lo haya empezado.";
        }

        return null;
    }

    /* ── Auxiliares ── */

    private function asegurarAlcance(Curso $grupo, ?Matricula $matricula = null, bool $cambioDeGrupo = false): void
    {
        if ($error = $this->noAlcanza($grupo, $matricula, $cambioDeGrupo)) {
            throw ValidationException::withMessages(['curso_id' => $error]);
        }
    }

    /** Bloquea al estudiante hasta el final de la transacción: sus matrículas se crean y cambian de a una. */
    private function bloquearEstudiante(int $id): void
    {
        User::whereKey($id)->lockForUpdate()->first();
    }

    /** La matrícula leída otra vez y bloqueada (la que llegó pudo cambiar mientras tanto). */
    private function bloquearMatricula(Matricula $m): Matricula
    {
        return Matricula::whereKey($m->id)->lockForUpdate()->firstOrFail();
    }

    /** Una sola matrícula vigente por estudiante (también lo exige la BD: matriculas.vigente_de). */
    private function asegurarSinVigente(int $estudianteId, ?int $salvo = null): void
    {
        $v = Matricula::where('estudiante_id', $estudianteId)->whereIn('estado', Matricula::VIGENTES)
            ->when($salvo, fn ($q) => $q->whereKeyNot($salvo))->first();
        if ($v) {
            throw ValidationException::withMessages(['estudiante_id' => "Ya tiene una matrícula vigente ({$v->codigo}): alguien la acaba de registrar. Recarga la página."]);
        }
    }

    /** Revisa el cupo con el grupo bloqueado: dos matrículas al tiempo no pueden pasarse del cupo. */
    private function asegurarCupo(Curso $grupo): void
    {
        $grupo = Curso::whereKey($grupo->id)->lockForUpdate()->firstOrFail();
        if ($grupo->cerrado()) {
            throw ValidationException::withMessages(['curso_id' => $grupo->motivoCerrado()]);
        }
        if ($grupo->cuposLibres() <= 0) {
            throw ValidationException::withMessages(['curso_id' => "El {$grupo->nombre} ({$grupo->codigo}) ya no tiene cupos libres."]);
        }
    }

    /* ── Historial de grupos (matricula_grupos, 27-11-2026) ── */

    /** Abre un paso de la matrícula por un grupo desde ese día (cierra antes el que siguiera abierto). */
    private function abrirPaso(Matricula $matricula, int $cursoId, CarbonInterface $desde): void
    {
        $this->cerrarPaso($matricula, $desde);
        MatriculaGrupo::create(['matricula_id' => $matricula->id, 'curso_id' => $cursoId, 'desde' => $desde->toDateString()]);
    }

    /** Cierra el paso abierto: ese día ya no cuenta en el grupo. */
    private function cerrarPaso(Matricula $matricula, CarbonInterface $hasta): void
    {
        MatriculaGrupo::where('matricula_id', $matricula->id)->whereNull('hasta')->update(['hasta' => $hasta->toDateString()]);
    }

    /**
     * Una matrícula anterior al historial no tiene pasos: se le crea el que llevaba (su grupo desde su inicio, cerrado si
     * estaba aplazada) para no perderlo al moverla.
     */
    private function asegurarHistorial(Matricula $matricula): void
    {
        if (! $matricula->curso_id || MatriculaGrupo::where('matricula_id', $matricula->id)->exists()) {
            return;
        }
        MatriculaGrupo::create([
            'matricula_id' => $matricula->id,
            'curso_id'     => $matricula->curso_id,
            'desde'        => $matricula->fecha_inicio->toDateString(),
            'hasta'        => $matricula->estado === 'activo' ? null : $matricula->estado_fecha?->toDateString(),
        ]);
    }

    private function moverDeGrupo(Matricula $matricula, Curso $nuevo): void
    {
        $anterior = $matricula->curso_id;
        $matricula->update(['curso_id' => $nuevo->id]);
        $this->sacarDeLista($matricula, $anterior);
        $matricula->estudiante->cursosComoEstudiante()->syncWithoutDetaching([$nuevo->id]);
    }

    /**
     * Quita al estudiante de la lista del grupo, salvo que otra matrícula suya (no anulada) siga
     * en ese grupo: por ejemplo un retiro anterior, que conserva su lugar en el historial del grupo.
     */
    private function sacarDeLista(Matricula $matricula, ?int $cursoId): void
    {
        if (! $cursoId) {
            return;
        }
        $otra = Matricula::where('estudiante_id', $matricula->estudiante_id)->where('curso_id', $cursoId)
            ->whereKeyNot($matricula->id)->where('estado', '!=', 'anulado')->exists();
        if (! $otra) {
            $matricula->estudiante->cursosComoEstudiante()->detach($cursoId);
        }
    }
}
