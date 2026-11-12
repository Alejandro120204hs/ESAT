<?php

namespace App\Services\Academico;

use App\Models\Academico\Curso;
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
            $estudiante->cursosComoEstudiante()->syncWithoutDetaching([$grupo->id]);

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
            $this->moverDeGrupo($matricula, $nuevo);
            $this->pagos->reprogramarDerecho($matricula->fresh('curso'));
        });
    }

    /**
     * aplazado / retirado: libera el cupo y anula las cuotas que vencen después de $fecha. El derecho
     *   de matrícula sin pagar que aún no vencía queda en espera (aplazado) o se anula (retirado).
     * graduado: termina el programa (el Request exige grupo finalizado, todos los módulos aprobados,
     *   plan pagado y paz y salvo).
     * activo (reintegro desde aplazado): vuelve a $grupo (con cupo), el derecho en espera vence al
     *   iniciar ese grupo y se generan las cuotas que faltan.
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
            if ($estado === 'activo') {
                $grupo ??= $matricula->curso;
                $this->asegurarCupo($grupo);
                if ($grupo->id !== $matricula->curso_id) {
                    $this->moverDeGrupo($matricula, $grupo);
                }
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
                // La siguiente cuota vence el día 10: si ya pasó este mes, empieza el próximo
                $desde = Carbon::parse($fecha);
                if ($desde->day > Pago::DIA_VENCIMIENTO) {
                    $desde->addMonthNoOverflow();
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
            $hoy = today();

            $vieja->update([
                'estado'        => 'retirado',
                'estado_fecha'  => $hoy,
                'estado_motivo' => "Cambio de programa a {$grupo->programa->nombre}: {$motivo}",
            ]);
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
            $vieja->estudiante->cursosComoEstudiante()->syncWithoutDetaching([$grupo->id]);

            if (! $derechoPagado) {
                $this->pagos->cobrarMatricula($nueva, $this->pagos->vencimientoDerecho($grupo));
            }
            $this->pagos->trasladarAdelantado($vieja->fresh(), $nueva->fresh());

            return $nueva->fresh();
        });
    }

    /* ── Auxiliares ── */

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
        if ($grupo->estado === 'finalizado') {
            throw ValidationException::withMessages(['curso_id' => "El {$grupo->nombre} ({$grupo->codigo}) ya finalizó."]);
        }
        if ($grupo->cuposLibres() <= 0) {
            throw ValidationException::withMessages(['curso_id' => "El {$grupo->nombre} ({$grupo->codigo}) ya no tiene cupos libres."]);
        }
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
