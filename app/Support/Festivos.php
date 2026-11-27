<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Festivos de Colombia (Ley 51 de 1983, "ley Emiliani"), calculados para cualquier año (decidido con el usuario el
 * 27-11-2026): en un festivo no hay clase, salvo que el docente tome asistencia ese día (Curso::claseEn).
 * - Fijos: 1 ene, 1 may, 20 jul, 7 ago, 8 dic y 25 dic.
 * - Se corren al lunes siguiente: Reyes (6 ene), San José (19 mar), San Pedro y San Pablo (29 jun), Asunción (15 ago),
 *   Día de la Raza (12 oct), Todos los Santos (1 nov) e Independencia de Cartagena (11 nov).
 * - Según la Pascua: Jueves y Viernes Santo, y corridos al lunes la Ascensión (+43 días), Corpus Christi (+64) y el
 *   Sagrado Corazón (+71).
 * Se pueden apagar con config('academico.festivos') (las pruebas que no son de festivos los apagan).
 */
class Festivos
{
    /** @var array<int, array<string, string>> año => [fecha => nombre] */
    private static array $cache = [];

    public static function activos(): bool
    {
        return (bool) config('academico.festivos', true);
    }

    /** El nombre del festivo de ese día, o null si no es festivo (o si están apagados). */
    public static function nombre(CarbonInterface|string $dia): ?string
    {
        if (! self::activos()) {
            return null;
        }
        $f = $dia instanceof CarbonInterface ? $dia->toDateString() : substr($dia, 0, 10);

        return self::delAnio((int) substr($f, 0, 4))[$f] ?? null;
    }

    public static function es(CarbonInterface|string $dia): bool
    {
        return self::nombre($dia) !== null;
    }

    /** [fecha => nombre] de los festivos entre dos fechas (incluidas). */
    public static function entre(string $desde, string $hasta): array
    {
        if (! self::activos() || $hasta < $desde) {
            return [];
        }
        $out = [];
        for ($a = (int) substr($desde, 0, 4); $a <= (int) substr($hasta, 0, 4); $a++) {
            foreach (self::delAnio($a) as $f => $n) {
                if ($f >= $desde && $f <= $hasta) {
                    $out[$f] = $n;
                }
            }
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, string> [fecha => nombre] del año */
    public static function delAnio(int $anio): array
    {
        if (isset(self::$cache[$anio])) {
            return self::$cache[$anio];
        }
        $dia = fn (int $m, int $d) => CarbonImmutable::create($anio, $m, $d);
        // Se corre al lunes siguiente si no cae en lunes
        $lunes = fn (CarbonImmutable $f) => $f->isMonday() ? $f : $f->next(CarbonInterface::MONDAY);
        $pascua = self::pascua($anio);

        $lista = [
            [$dia(1, 1), 'Año Nuevo'],
            [$lunes($dia(1, 6)), 'Día de los Reyes Magos'],
            [$lunes($dia(3, 19)), 'Día de San José'],
            [$pascua->subDays(3), 'Jueves Santo'],
            [$pascua->subDays(2), 'Viernes Santo'],
            [$dia(5, 1), 'Día del Trabajo'],
            [$pascua->addDays(43), 'Ascensión del Señor'],
            [$pascua->addDays(64), 'Corpus Christi'],
            [$pascua->addDays(71), 'Sagrado Corazón'],
            [$lunes($dia(6, 29)), 'San Pedro y San Pablo'],
            [$dia(7, 20), 'Día de la Independencia'],
            [$dia(8, 7), 'Batalla de Boyacá'],
            [$lunes($dia(8, 15)), 'Asunción de la Virgen'],
            [$lunes($dia(10, 12)), 'Día de la Raza'],
            [$lunes($dia(11, 1)), 'Todos los Santos'],
            [$lunes($dia(11, 11)), 'Independencia de Cartagena'],
            [$dia(12, 8), 'Inmaculada Concepción'],
            [$dia(12, 25), 'Navidad'],
        ];
        $out = [];
        foreach ($lista as [$f, $n]) {
            $out[$f->toDateString()] = $n;
        }
        ksort($out);

        return self::$cache[$anio] = $out;
    }

    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher, calendario gregoriano). */
    private static function pascua(int $a): CarbonImmutable
    {
        $b = intdiv($a, 100);
        $c = $a % 100;
        $d = (19 * ($a % 19) + $b - intdiv($b, 4) - intdiv($b - intdiv($b + 8, 25) + 1, 3) + 15) % 30;
        $e = (32 + 2 * ($b % 4) + 2 * intdiv($c, 4) - $d - $c % 4) % 7;
        $f = intdiv($a % 19 + 11 * $d + 22 * $e, 451);
        $mes = intdiv($d + $e - 7 * $f + 114, 31);
        $dia = ($d + $e - 7 * $f + 114) % 31 + 1;

        return CarbonImmutable::create($a, $mes, $dia);
    }
}
