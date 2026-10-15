<?php

namespace Tests\Feature\Admin;

use App\Models\Academico\Curso;
use App\Models\Academico\DocentePerfil;
use App\Models\Academico\Escuela;
use App\Models\Academico\Programa;
use App\Models\Institucional\Sede;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Datos mínimos del panel Admin para las pruebas: sede con su admin, escuela,
 * programas ofertados, docentes y grupos. Llamar prepararSede() en setUp().
 */
trait DatosAcademicos
{
    protected Sede $sede;
    protected User $admin;
    protected Escuela $escuela;
    private int $consecutivo = 0;

    protected function prepararSede(): void
    {
        $this->sede    = $this->otraSede('Sede de prueba', 'Sasaima');
        $this->admin   = User::factory()->create(['role' => 'admin', 'sede_id' => $this->sede->id]);
        $this->escuela = Escuela::create(['nombre' => 'Administrativa', 'sigla' => 'ADM']);
    }

    protected function otraSede(string $nombre = 'Otra sede', string $ciudad = 'La Dorada'): Sede
    {
        return Sede::create(['nombre' => $nombre, 'departamento' => 'Cundinamarca', 'ciudad' => $ciudad, 'direccion' => 'Calle 1 # 2-3']);
    }

    protected function programa(array $jornadas = ['Mañana', 'Tarde'], int $horas = 1600, int $meses = 12, int $cupo = 25): Programa
    {
        $n = ++$this->consecutivo;
        $programa = Programa::create([
            'escuela_id'        => $this->escuela->id,
            'codigo'            => sprintf('ESA-ADM-%03d', $n),
            'nombre'            => "Programa de prueba {$n}",
            'nivel'             => 'Técnico Laboral',
            'modalidad'         => 'Presencial',
            'horas'             => $horas,
            'jornadas'          => $jornadas,
            'cupo_grupo'        => $cupo,
            'precio_total'      => 3000000,
            'matricula'         => 200000,
            'duracion_meses'    => $meses,
            'tipo_periodo'      => 'semestre',
            'duracion_periodos' => Programa::calcularPeriodos($meses, 'semestre'),
            'estado'            => 'activo',
        ]);
        $programa->sedes()->attach($this->sede->id);

        return $programa;
    }

    protected function docente(string $estado = 'activo', ?Sede $sede = null): User
    {
        $docente = User::factory()->create(['role' => 'docente', 'sede_id' => ($sede ?? $this->sede)->id]);
        DocentePerfil::create(['user_id' => $docente->id, 'escuela_id' => $this->escuela->id, 'vinculacion' => 'hora_catedra', 'estado' => $estado]);

        return $docente;
    }

    protected function grupo(
        Programa $programa,
        string $jornada,
        ?User $docente = null,
        string $estado = 'activo',
        string $inicio = '2026-02-02',
        array $sesiones = [],
        ?Sede $sede = null,
    ): Curso {
        $n     = ++$this->consecutivo;
        $fecha = Carbon::parse($inicio);
        $grupo = Curso::create([
            'programa_id'  => $programa->id,
            'sede_id'      => ($sede ?? $this->sede)->id,
            'codigo'       => "CUR-ADM-{$n}-A",
            'nombre'       => "Grupo {$n}",
            'jornada'      => $jornada,
            'fecha_inicio' => $fecha,
            'fecha_fin'    => Curso::finPara($fecha, $programa),
            'estado'       => $estado,
        ]);
        if ($docente) {
            $grupo->docentes()->attach($docente->id);
        }
        if ($sesiones) {
            $grupo->sesiones()->createMany($sesiones);
        }

        return $grupo;
    }

    protected function sesion(string $dia, string $inicio, string $fin): array
    {
        return ['dia' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin];
    }
}
