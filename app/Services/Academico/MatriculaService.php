<?php

namespace App\Services\Academico;

use App\Models\Academico\Curso;
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
            $this->asegurarCupo($grupo);

            $matricula = Matricula::create([
                'codigo'        => Matricula::siguienteCodigo(),
                'estudiante_id' => $estudiante->id,
                'programa_id'   => $grupo->programa_id,
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
