{{-- Calificaciones — MAQUETA (solo frontend, sin controlador ni BD).
     Reales: los grupos con clases de la sede 7, sus programas, módulos (malla con horas), horarios y docentes.
     De ejemplo (ficticios): los estudiantes, sus documentos/teléfonos, las notas, las habilitaciones y el % de asistencia.
     Reglas ya definidas: nota por módulo de 1.0 a 5.0 (aprueba 3.0); la pone el docente y el Admin solo consulta;
     una sola habilitación por módulo perdido; las fechas de cada módulo se calculan con el horario del grupo y las
     horas de cada módulo en orden (lo que hará el servicio cuando exista el backend). --}}
@extends('admin.layout')

@section('title', 'Calificaciones')
@section('page-title', 'Calificaciones')
@section('active', 'calificaciones')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/calificaciones.css') }}?v={{ filemtime(public_path('assets/css/admin/calificaciones.css')) }}">
@endpush

@php
/* ── Datos reales de la sede 7 (programas, mallas, grupos, horarios, docentes) ── */
$MALLAS = [
    1  => [['Fundamentos de gerontología', 160], ['Cuidado básico de enfermería', 240], ['Nutrición en el adulto mayor', 120], ['Actividades terapéuticas', 160], ['Ética y legislación en salud', 80], ['Primeros auxilios', 80], ['Práctica clínica', 960]],
    2  => [['Introducción a la salud mental', 160], ['Psicopatología básica', 200], ['Cuidado al paciente psiquiátrico', 240], ['Farmacología en psiquiatría', 80], ['Ética y derechos del paciente', 80], ['Práctica clínica', 1040]],
    3  => [['Fundamentos de SST', 160], ['Identificación y control de riesgos', 200], ['Normativa colombiana SST', 120], ['Ergonomía y bienestar laboral', 120], ['Primeros auxilios y emergencias', 80], ['Práctica empresarial SST', 1120]],
    4  => [['Técnicas básicas de cocina', 200], ['Gastronomía colombiana', 160], ['Cocina internacional', 200], ['Panadería y repostería', 160], ['Higiene y seguridad alimentaria', 80], ['Práctica en cocina profesional', 800]],
    6  => [['Sistema de salud colombiano', 120], ['Codificación CIE-10 y CUPS', 200], ['Facturación y glosas', 160], ['Auditoría de cuentas médicas', 160], ['Normativa SOAT y PGSS', 80], ['Práctica supervisada', 880]],
    7  => [['Contabilidad general', 200], ['Ofimática avanzada', 120], ['Nómina y prestaciones sociales', 160], ['Tributaria básica', 120], ['Gestión documental', 80], ['Práctica empresarial', 760]],
    8  => [['Desarrollo infantil y psicología educativa', 200], ['Pedagogía para la primera infancia', 200], ['Lúdica y expresión artística', 160], ['Nutrición y salud infantil', 80], ['Legislación educativa colombiana', 80], ['Práctica pedagógica', 880]],
    9  => [['Fundamentos de electricidad', 200], ['Instalaciones eléctricas residenciales', 240], ['Instalaciones industriales', 200], ['RETIE y normativa eléctrica', 80], ['Práctica en campo', 880]],
    10 => [['Electricidad y magnetismo aplicada', 160], ['Motores eléctricos y transformadores', 200], ['Automatización industrial básica', 200], ['Mantenimiento preventivo y correctivo', 160], ['Práctica industrial', 880]],
];
$DOC = [
    'gonzalez' => ['María González Ruiz', '3101234567'],   'rios'   => ['Alejandro Ríos Mora', '3159876543'],
    'martinez' => ['Laura Martínez Peña', '3124567890'],   'zapata' => ['Andrés Zapata Villa', '3183216540'],
    'lopez'    => ['Patricia López Castro', '3146543210'], 'diaz'   => ['Roberto Díaz Sierra', '3167890123'],
    'vargas'   => ['Diana Vargas Nieto', '3135678901'],    'medina' => ['Fabián Medina', '3173456789'],
    'mora'     => ['Adriana Mora Parra', '3154567890'],
];
// [id, programa_id, programa, grupo, jornada, sigla, escuela, inicio, meses, sesiones [día, inicio, fin], docente, módulo sin nota (índice) o null]
$BASE = [
    [1,  1,  'Técnico Laboral en Servicios Geriátricos', 'Grupo A', 'Mañana', 'sal', 'Salud', '2026-02-02', 12, [['Lunes', '08:00', '12:00'], ['Miércoles', '08:00', '12:00'], ['Viernes', '08:00', '12:00']], 'gonzalez', null],
    [2,  1,  'Técnico Laboral en Servicios Geriátricos', 'Grupo B', 'Tarde', 'sal', 'Salud', '2026-02-02', 12, [['Martes', '14:00', '18:00'], ['Jueves', '14:00', '18:00']], 'rios', 5],
    [3,  2,  'Técnico Laboral en Auxiliar de Psiquiatría', 'Grupo A', 'Mañana', 'sal', 'Salud', '2026-02-02', 12, [['Martes', '08:00', '12:00'], ['Jueves', '08:00', '12:00']], 'gonzalez', null],
    [4,  2,  'Técnico Laboral en Auxiliar de Psiquiatría', 'Grupo B', 'Noche', 'sal', 'Salud', '2026-02-02', 12, [['Lunes', '18:00', '21:00'], ['Miércoles', '18:00', '21:00'], ['Viernes', '18:00', '21:00']], 'rios', null],
    [5,  3,  'Técnico Laboral en Seguridad Ocupacional y Laboral', 'Grupo A', 'Fines de semana', 'sal', 'Salud', '2026-02-02', 12, [['Sábado', '07:00', '13:00']], 'rios', null],
    [6,  4,  'Técnico Laboral en Cocina Nacional e Internacional', 'Grupo A', 'Mañana', 'tur', 'Cocina y Turismo', '2026-02-02', 10, [['Lunes', '07:00', '11:00'], ['Martes', '07:00', '11:00'], ['Jueves', '08:00', '12:00']], 'martinez', null],
    [7,  4,  'Técnico Laboral en Cocina Nacional e Internacional', 'Grupo B', 'Tarde', 'tur', 'Cocina y Turismo', '2026-02-02', 10, [['Lunes', '13:00', '17:00'], ['Miércoles', '13:00', '17:00'], ['Viernes', '13:00', '17:00']], 'zapata', null],
    [9,  6,  'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas', 'Grupo A', 'Tarde', 'adm', 'Administrativa', '2026-02-02', 10, [['Lunes', '14:00', '18:00'], ['Miércoles', '14:00', '18:00'], ['Jueves', '14:00', '18:00'], ['Viernes', '14:00', '18:00']], 'lopez', 4],
    [10, 6,  'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas', 'Grupo B', 'Fines de semana', 'adm', 'Administrativa', '2026-02-02', 10, [['Sábado', '08:00', '14:00']], 'diaz', null],
    [11, 7,  'Técnico Laboral en Auxiliar Contable y Administrativo', 'Grupo A', 'Mañana', 'adm', 'Administrativa', '2026-02-02', 9, [['Lunes', '08:00', '11:00'], ['Miércoles', '08:00', '11:00'], ['Viernes', '08:00', '11:00']], 'diaz', null],
    [12, 7,  'Técnico Laboral en Auxiliar Contable y Administrativo', 'Grupo B', 'Noche', 'adm', 'Administrativa', '2025-08-04', 9, [['Martes', '18:00', '21:00'], ['Jueves', '18:00', '21:00']], 'lopez', null],
    [13, 8,  'Técnico Laboral en Asistente de Preescolar', 'Grupo A', 'Mañana', 'edu', 'Educación e Idiomas', '2026-02-02', 10, [['Lunes', '07:00', '10:00'], ['Martes', '07:00', '10:00'], ['Miércoles', '07:00', '10:00'], ['Jueves', '07:00', '10:00'], ['Viernes', '07:00', '10:00']], 'vargas', null],
    [14, 9,  'Técnico Laboral en Electricista', 'Grupo A', 'Noche', 'cie', 'Ciencias', '2026-02-02', 10, [['Lunes', '18:00', '21:00'], ['Miércoles', '18:00', '21:00'], ['Viernes', '18:00', '22:00']], 'medina', 3],
    [15, 10, 'Técnico Laboral en Electromecánica', 'Grupo A', 'Tarde', 'cie', 'Ciencias', '2026-02-02', 10, [['Martes', '14:00', '18:00'], ['Jueves', '14:00', '18:00']], 'mora', null],
];

/* ── Estudiantes y notas de ejemplo (ficticios, generados siempre iguales) ── */
mt_srand(2026);
$NOMBRES   = ['Valentina', 'Santiago', 'Camila', 'Sebastián', 'Daniela', 'Juan David', 'Mariana', 'Andrés Felipe', 'Laura Sofía', 'Kevin', 'Natalia', 'Brayan', 'Paula Andrea', 'Cristian', 'Yuliana', 'Jhon Fredy', 'Luisa Fernanda', 'Diego', 'Karen', 'Óscar', 'Angie', 'Miguel Ángel', 'Lina Marcela', 'Esteban', 'Leidy', 'Wilson', 'Tatiana', 'Jorge Iván', 'Sara', 'Nicolás', 'Ximena', 'Fredy', 'Yesica', 'Julián', 'Erika', 'Mauricio'];
$APELLIDOS = ['Rodríguez', 'Gómez', 'Martínez', 'Herrera', 'Castro', 'Ramírez', 'Torres', 'Sánchez', 'Rojas', 'Moreno', 'Jiménez', 'Cárdenas', 'Ospina', 'Suárez', 'Pineda', 'Cifuentes', 'Garzón', 'Rincón', 'Parra', 'Bermúdez', 'Quintero', 'Salazar', 'Beltrán', 'Acosta', 'Velásquez', 'Patiño', 'Buitrago', 'Londoño', 'Ortiz', 'Murillo'];
$rnd = fn (float $a, float $b) => $a + (mt_rand() / mt_getrandmax()) * ($b - $a);
$nota = fn (float $x) => round(max(1.0, min(5.0, $x)), 1);

$hoy      = now()->startOfDay();
$DIA_ISO  = ['Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4, 'Viernes' => 5, 'Sábado' => 6];
$DIA_C    = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb'];
$MES_C    = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$corta    = fn ($f) => $f->day . ' ' . $MES_C[$f->month - 1] . ($f->year !== $hoy->year ? ' ' . $f->year : '');

$grupos = []; $estudiantes = []; $eid = 1;
foreach ($BASE as [$gid, $pid, $programa, $grupo, $jornada, $sigla, $escuela, $ini, $meses, $sesiones, $docKey, $sinNota]) {
    $inicio = \Carbon\Carbon::parse($ini);
    $fin    = $inicio->copy()->addMonthsNoOverflow($meses);   // Curso::finPara

    // Días de clase entre el inicio y el fin del grupo, según su horario
    $dias = array_map(fn ($s) => $DIA_ISO[$s[0]], $sesiones);
    $clases = [];
    for ($d = $inicio->copy(); $d->lt($fin); $d->addDay()) {
        if (in_array($d->dayOfWeekIso, $dias, true)) $clases[] = $d->copy();
    }
    // Cada módulo ocupa, en orden, la parte de las clases que le toca por sus horas
    $malla = $MALLAS[$pid]; $total = array_sum(array_column($malla, 1)); $n = count($clases); $acum = 0;
    $modulos = [];
    foreach ($malla as $i => [$nombre, $horas]) {
        $desde = (int) floor($n * $acum / $total); $acum += $horas;
        $hasta = max($desde, (int) floor($n * $acum / $total) - 1);
        $mIni = $clases[$desde]; $mFin = $clases[min($hasta, $n - 1)];
        $estado = $mFin->lt($hoy) ? 'terminado' : ($mIni->lte($hoy) ? 'en_curso' : 'por_iniciar');
        $modulos[] = ['nombre' => $nombre, 'horas' => $horas, 'ini' => $mIni->toDateString(), 'fin' => $mFin->toDateString(),
            'ini_txt' => $corta($mIni), 'fin_txt' => $corta($mFin), 'estado' => $estado, 'sin_nota' => $estado === 'terminado' && $sinNota === $i];
    }

    // Horario en texto: los días con la misma hora se juntan
    $porHora = [];
    foreach ($sesiones as [$dia, $h1, $h2]) $porHora["{$h1}–{$h2}"][] = $DIA_C[$DIA_ISO[$dia]];
    $horario = collect($porHora)->map(fn ($ds, $h) => implode(', ', $ds) . ' · ' . $h)->implode("\n");

    $g = ['id' => $gid, 'programa' => $programa, 'grupo' => $grupo, 'jornada' => $jornada, 'sigla' => $sigla, 'escuela' => $escuela,
        'docente' => $DOC[$docKey][0], 'telefono' => $DOC[$docKey][1], 'horario' => $horario,
        'inicio_txt' => $corta($inicio), 'fin_txt' => $corta($fin), 'terminado' => $fin->lte($hoy), 'modulos' => $modulos];

    // Estudiantes del grupo con sus notas por módulo
    $cuantos = mt_rand(7, 11);
    for ($k = 0; $k < $cuantos; $k++) {
        $nivel = $rnd(3.1, 4.7);
        $notas = [];
        foreach ($modulos as $m) {
            if ($m['estado'] !== 'terminado' || $m['sin_nota']) { $notas[] = null; continue; }
            $n1 = $nota($nivel + $rnd(-0.6, 0.5));
            $h = null;
            if ($n1 < 3.0 && mt_rand(1, 100) <= 65) $h = $nota($rnd(2.6, 4.2));
            $notas[] = ['n' => $n1, 'h' => $h];
        }
        $menor = mt_rand(1, 100) <= 12;
        $estudiantes[] = ['id' => $eid++, 'grupo_id' => $gid,
            'nombres' => $NOMBRES[mt_rand(0, count($NOMBRES) - 1)],
            'apellidos' => $APELLIDOS[mt_rand(0, count($APELLIDOS) - 1)] . ' ' . $APELLIDOS[mt_rand(0, count($APELLIDOS) - 1)],
            'tipo_doc' => $menor ? 'TI' : 'CC', 'documento' => (string) ($menor ? mt_rand(1100000000, 1109999999) : mt_rand(1000000000, 1079999999)),
            'telefono' => '3' . mt_rand(0, 2) . mt_rand(0, 9) . mt_rand(1000000, 9999999),
            'asistencia' => mt_rand(72, 99), 'notas' => $notas];
    }
    $grupos[] = $g;
}

/* ── Resumen por estudiante (lo que hará el servicio): estado de cada módulo, promedio y situación ── */
$gruposPorId = collect($grupos)->keyBy('id');
foreach ($estudiantes as &$e) {
    $g = $gruposPorId[$e['grupo_id']];
    $estados = []; $finales = []; $porHab = 0; $reprob = 0; $aprob = 0;
    foreach ($g['modulos'] as $i => $m) {
        $x = $e['notas'][$i];
        if ($x === null) { $estados[] = $m['estado'] === 'terminado' ? 'sin_nota' : $m['estado']; continue; }
        $final = $x['h'] ?? $x['n']; $finales[] = $final;
        if ($x['n'] >= 3.0)      { $estados[] = 'aprobado'; $aprob++; }
        elseif ($x['h'] === null) { $estados[] = 'por_habilitar'; $porHab++; }
        elseif ($x['h'] >= 3.0)  { $estados[] = 'habilito'; $aprob++; }
        else                     { $estados[] = 'reprobado'; $reprob++; }
    }
    $e['estados']   = $estados;
    $e['prom']      = $finales ? round(array_sum($finales) / count($finales), 1) : null;
    $e['aprobados'] = $aprob;
    $e['total']     = count($g['modulos']);
    $e['por_habilitar'] = $porHab;
    $e['reprobados']    = $reprob;
    $e['situacion'] = $reprob ? 'reprobado' : ($porHab ? 'habilitar' : ($g['terminado'] && $aprob === $e['total'] ? 'listo' : ($finales ? 'al_dia' : 'sin_notas')));
}
unset($e);

/* ── Resumen por grupo y de la sede ── */
$estudiantes = collect($estudiantes);
foreach ($grupos as &$g) {
    $suyos = $estudiantes->where('grupo_id', $g['id']);
    foreach ($g['modulos'] as $i => &$m) {
        $fin = $suyos->map(fn ($e) => $e['notas'][$i] === null ? null : ($e['notas'][$i]['h'] ?? $e['notas'][$i]['n']))->filter(fn ($v) => $v !== null);
        $m['prom'] = $fin->isEmpty() ? null : round($fin->avg(), 1);
    }
    unset($m);
    $actual = collect($g['modulos'])->search(fn ($m) => $m['estado'] === 'en_curso');
    $g['actual']     = $actual === false ? null : $actual;
    $g['terminados'] = collect($g['modulos'])->where('estado', 'terminado')->count();
    $g['activos']    = $suyos->count();
    $proms           = $suyos->pluck('prom')->filter(fn ($v) => $v !== null);
    $g['prom']       = $proms->isEmpty() ? null : round($proms->avg(), 1);
    $g['por_habilitar'] = $suyos->where('situacion', 'habilitar')->count();
    $g['reprobados']    = $suyos->where('situacion', 'reprobado')->count();
    $g['listos']        = $suyos->where('situacion', 'listo')->count();
    $g['sin_nota']      = collect($g['modulos'])->where('sin_nota', true)->keys()->values()->all();
}
unset($g);
$grupos = collect($grupos);

$promsSede   = $estudiantes->pluck('prom')->filter(fn ($v) => $v !== null);
$promSede    = $promsSede->isEmpty() ? null : round($promsSede->avg(), 1);
$porHabilitar = $estudiantes->where('situacion', 'habilitar')->count();
$reprobados  = $estudiantes->where('situacion', 'reprobado')->count();
$modSinNota  = $grupos->sum(fn ($g) => count($g['sin_nota']));
$docSinNota  = $grupos->filter(fn ($g) => count($g['sin_nota']))->pluck('docente')->unique()->count();
$listos      = $estudiantes->where('situacion', 'listo')->count();
$jornadas    = $grupos->pluck('jornada')->unique()->sort()->values();

$fmt       = fn ($v) => $v === null ? '—' : number_format($v, 1, ',', '');
$tonoNota  = fn ($v) => $v === null ? 'none' : ($v >= 4.0 ? 'ok' : ($v >= 3.0 ? 'warn' : 'bad'));
$iniciales = fn ($n, $a) => mb_strtoupper(mb_substr($n, 0, 1) . mb_substr($a, 0, 1));
$SITUACION = [
    'listo'     => ['app-status-active', 'Listo para graduar'],
    'al_dia'    => ['app-status-muted',  'Al día'],
    'habilitar' => ['app-status-warn',   'Por habilitar'],
    'reprobado' => ['app-status-danger', 'Perdió la habilitación'],
    'sin_notas' => ['app-status-muted',  'Sin notas aún'],
];
$sede = auth()->user()->sede;
@endphp

@section('content')
<div class="cal-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="cal-header">
        <div>
            <h2 class="cal-title">Calificaciones</h2>
            <p class="cal-sub">Consulta las notas que ponen los docentes en cada módulo del programa. Escala de 1,0 a 5,0: se aprueba con 3,0 y quien pierde un módulo tiene una habilitación. Para graduarse hay que tener todos los módulos aprobados.</p>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="cal-kpis">
        <div class="cal-kpi-chip cal-kpi-blue">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $fmt($promSede) }}</div>
                <div class="cal-kpi-lbl">Promedio de la sede</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-green">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $listos }}</div>
                <div class="cal-kpi-lbl">Listos para graduar (notas)</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-orange">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $modSinNota }}</div>
                <div class="cal-kpi-lbl">Módulos terminados sin nota · {{ $docSinNota }} {{ $docSinNota === 1 ? 'docente' : 'docentes' }}</div>
            </div>
        </div>
        <div class="cal-kpi-chip cal-kpi-red">
            <div class="cal-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="cal-kpi-val">{{ $porHabilitar }}</div>
                <div class="cal-kpi-lbl">Por habilitar{{ $reprobados ? ' · ' . $reprobados . ' perdieron la habilitación' : '' }}</div>
            </div>
        </div>
    </div>

    {{-- Vistas --}}
    <div class="cal-vistas" role="tablist" aria-label="Vista">
        <button type="button" class="cal-vista-btn is-active" data-vista="grupos" role="tab" aria-selected="true">
            Por grupo <span class="cal-vista-num {{ $modSinNota ? 'is-aviso' : '' }}">{{ $grupos->count() }}</span>
        </button>
        <button type="button" class="cal-vista-btn" data-vista="estudiantes" role="tab" aria-selected="false">
            Por estudiante <span class="cal-vista-num {{ $porHabilitar + $reprobados ? 'is-aviso' : '' }}">{{ $estudiantes->count() }}</span>
        </button>
    </div>

    {{-- ═══════════ Por grupo ═══════════ --}}
    <section class="cal-vista" data-panel="grupos">
        <div class="cal-filters">
            <div class="cal-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="cal-g-search" placeholder="Buscar por programa, grupo o docente..." autocomplete="off" aria-label="Buscar grupo">
            </div>
            <div class="app-combobox" id="cal-g-cb-jornada" data-position="absolute" data-options='@json($jornadas)'>
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las jornadas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-g-jornada" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-g-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-g-situacion" data-combobox-value>
            </div>
            <span class="cal-count-badge" id="cal-g-count"></span>
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-exportar="grupos" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="cal-table-wrap">
            <table class="cal-table" id="cal-g-table">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Docente</th>
                        <th>Avance de módulos</th>
                        <th>Promedio</th>
                        <th>Pendientes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grupos as $g)
                    @php
                        $nMod = count($g['modulos']);
                        $mAct = $g['actual'] !== null ? $g['modulos'][$g['actual']] : null;
                    @endphp
                    <tr class="cal-row" data-id="{{ $g['id'] }}"
                        data-buscar="{{ mb_strtolower($g['programa'].' '.$g['grupo'].' '.$g['docente'].' '.$g['escuela']) }}"
                        data-jornada="{{ $g['jornada'] }}"
                        data-sin="{{ count($g['sin_nota']) }}" data-hab="{{ $g['por_habilitar'] + $g['reprobados'] }}"
                        data-listos="{{ $g['listos'] }}" data-terminado="{{ $g['terminado'] ? 1 : 0 }}">
                        <td>
                            <div class="cal-person-cell">
                                <div class="cal-avatar cal-esc-{{ $g['sigla'] }}">{{ mb_substr($g['grupo'], -1) }}</div>
                                <div>
                                    <div class="cal-person-name">{{ $g['programa'] }}</div>
                                    <div class="cal-person-sub">{{ $g['grupo'] }} · {{ $g['jornada'] }} · {{ $g['activos'] }} estudiantes</div>
                                </div>
                            </div>
                        </td>
                        <td class="cal-muted-cell">{{ $g['docente'] }}</td>
                        <td>
                            <div class="cal-avance" aria-label="{{ $g['terminados'] }} de {{ $nMod }} módulos terminados">
                                @foreach($g['modulos'] as $m)
                                    <span class="cal-avance-seg is-{{ $m['sin_nota'] ? 'sin' : $m['estado'] }}" title="{{ $loop->iteration }}. {{ $m['nombre'] }}"></span>
                                @endforeach
                            </div>
                            <div class="cal-person-sub">
                                @if($g['terminado'])
                                    Grupo terminado el {{ $g['fin_txt'] }}
                                @elseif($mAct)
                                    En curso: {{ $g['actual'] + 1 }} de {{ $nMod }} · {{ $mAct['nombre'] }}
                                @else
                                    {{ $g['terminados'] }} de {{ $nMod }} terminados
                                @endif
                            </div>
                        </td>
                        <td><span class="cal-nota cal-tono-{{ $tonoNota($g['prom']) }}">{{ $fmt($g['prom']) }}</span></td>
                        <td>
                            <div class="cal-tags">
                                @if(count($g['sin_nota']))
                                    <span class="cal-sin-tag">{{ count($g['sin_nota']) === 1 ? '1 módulo sin nota' : count($g['sin_nota']).' módulos sin nota' }}</span>
                                @endif
                                @if($g['por_habilitar'])
                                    <span class="cal-hab-tag">{{ $g['por_habilitar'] }} por habilitar</span>
                                @endif
                                @if($g['reprobados'])
                                    <span class="cal-rep-tag">{{ $g['reprobados'] }} {{ $g['reprobados'] === 1 ? 'reprobó' : 'reprobaron' }}</span>
                                @endif
                                @if($g['listos'])
                                    <span class="app-status-tag app-status-active">{{ $g['listos'] }} {{ $g['listos'] === 1 ? 'listo' : 'listos' }} para graduar</span>
                                @endif
                                @if(! count($g['sin_nota']) && ! $g['por_habilitar'] && ! $g['reprobados'] && ! $g['listos'])
                                    <span class="cal-person-sub">Ninguno</span>
                                @endif
                            </div>
                        </td>
                        <td class="cal-actions-cell">
                            <button type="button" class="cal-icon-btn" title="Ver notas del grupo" aria-label="Ver notas de {{ $g['programa'] }} {{ $g['grupo'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cal-empty" id="cal-g-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $grupos->isEmpty() ? 'Tu sede todavía no tiene grupos con clases.' : 'No hay grupos que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="cal-pagination" id="cal-g-pagination" hidden></div>
    </section>

    {{-- ═══════════ Por estudiante ═══════════ --}}
    <section class="cal-vista" data-panel="estudiantes" hidden>
        <div class="cal-filters">
            <div class="cal-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" id="cal-e-search" placeholder="Buscar por nombre o documento..." autocomplete="off" aria-label="Buscar estudiante">
            </div>
            <div class="app-combobox" id="cal-e-cb-grupo" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-e-cb-situacion" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las situaciones</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-situacion" data-combobox-value>
            </div>
            <div class="app-combobox" id="cal-e-cb-orden" data-position="absolute">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Menor promedio primero</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="cal-e-orden" data-combobox-value>
            </div>
            <span class="cal-count-badge" id="cal-e-count"></span>
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-exportar="estudiantes" title="Descarga la lista filtrada para abrirla en Excel">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Exportar
            </button>
        </div>

        <div class="cal-table-wrap">
            <table class="cal-table" id="cal-e-table">
                <thead>
                    <tr>
                        <th>Estudiante</th>
                        <th>Programa y grupo</th>
                        <th>Módulos aprobados</th>
                        <th>Promedio</th>
                        <th>Situación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($estudiantes as $e)
                    @php $g = $gruposPorId[$e['grupo_id']]; $pctAprob = $e['total'] ? round($e['aprobados'] * 100 / $e['total']) : 0; @endphp
                    <tr class="cal-row" data-id="{{ $e['id'] }}"
                        data-buscar="{{ mb_strtolower($e['nombres'].' '.$e['apellidos'].' '.$e['documento']) }}"
                        data-grupo="{{ $e['grupo_id'] }}" data-situacion="{{ $e['situacion'] }}"
                        data-prom="{{ $e['prom'] ?? 9 }}" data-perdidos="{{ $e['por_habilitar'] + $e['reprobados'] }}"
                        data-nombre="{{ mb_strtolower($e['apellidos'].' '.$e['nombres']) }}">
                        <td>
                            <div class="cal-person-cell">
                                <div class="cal-avatar cal-esc-{{ $g['sigla'] }}">{{ $iniciales($e['nombres'], $e['apellidos']) }}</div>
                                <div>
                                    <div class="cal-person-name">{{ $e['nombres'] }} {{ $e['apellidos'] }}</div>
                                    <div class="cal-person-sub">{{ $e['tipo_doc'] }} {{ $e['documento'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cal-prog">{{ $g['programa'] }}</div>
                            <div class="cal-person-sub">{{ $g['grupo'] }} · {{ $g['jornada'] }}</div>
                        </td>
                        <td>
                            <div class="cal-meter-cell">
                                <span class="cal-meter"><span style="width: {{ $pctAprob }}%"></span></span>
                                <span class="cal-num">{{ $e['aprobados'] }}</span><span class="cal-de">de {{ $e['total'] }}</span>
                            </div>
                        </td>
                        <td><span class="cal-nota cal-tono-{{ $tonoNota($e['prom']) }}">{{ $fmt($e['prom']) }}</span></td>
                        <td><span class="app-status-tag {{ $SITUACION[$e['situacion']][0] }}">{{ $SITUACION[$e['situacion']][1] }}</span></td>
                        <td class="cal-actions-cell">
                            <button type="button" class="cal-icon-btn" title="Ver notas" aria-label="Ver notas de {{ $e['nombres'] }}">
                                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="cal-empty" id="cal-e-empty" hidden>
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <p>{{ $estudiantes->isEmpty() ? 'Todavía no hay estudiantes activos en grupos con clases.' : 'No hay estudiantes que coincidan con la búsqueda.' }}</p>
            </div>
        </div>
        <div class="cal-pagination" id="cal-e-pagination" hidden></div>
    </section>

</div>{{-- /cal-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Notas del grupo / notas del estudiante
     (un solo cajón; el contenido lo arma calificaciones.js)
     ═══════════════════════════════════════════════════════ --}}
<div class="cal-drawer" id="cal-drawer" aria-hidden="true">
    <div class="cal-drawer-overlay" data-cerrar></div>
    <div class="cal-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="cal-dw-titulo">
        <div class="cal-drawer-head">
            <div class="cal-drawer-head-left">
                <button type="button" class="cal-close-btn" id="cal-dw-volver" aria-label="Volver" hidden>
                    <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
                <div class="cal-drawer-avatar" id="cal-dw-avatar"></div>
                <div class="cal-drawer-head-info">
                    <h3 class="cal-drawer-title" id="cal-dw-titulo"></h3>
                    <div class="cal-drawer-sub" id="cal-dw-sub"></div>
                    <div class="cal-drawer-badges" id="cal-dw-badges"></div>
                </div>
            </div>
            <button type="button" class="cal-close-btn" data-cerrar aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="cal-drawer-body" id="cal-dw-body"></div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     CERTIFICADO DE NOTAS — vista previa e impresión
     (datos legales reales de ESAT; las notas las arma calificaciones.js)
     ═══════════════════════════════════════════════════════ --}}
<div class="cal-cert-modal" id="cal-cert" aria-hidden="true">
    <div class="cal-cert-overlay" data-cert-cerrar></div>
    <div class="cal-cert-dialog" role="dialog" aria-modal="true" aria-label="Certificado de notas">
        <div class="cal-cert-acciones">
            <button type="button" class="cal-btn cal-btn-ghost cal-btn-sm" data-cert-cerrar>Cerrar</button>
            <button type="button" class="cal-btn cal-btn-primary cal-btn-sm" id="cal-cert-imprimir">
                <svg viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir o guardar en PDF
            </button>
        </div>
        <article class="cal-hoja" id="cal-hoja">
            <header class="cal-hoja-cab">
                <div class="cal-hoja-marca">
                    <img src="{{ asset('assets/img/logo.png') }}" alt="ESAT">
                    <div>
                        <strong>Escuela Nacional de Educación ESAT</strong>
                        <span>NIT 900820764-1 · Lic. Funcionamiento 5850</span>
                        @if($sede)
                            <span>Sede {{ $sede->nombre }}@if($sede->direccion) · {{ $sede->direccion }}@endif @if($sede->ciudad) · {{ $sede->ciudad }}@endif</span>
                        @endif
                    </div>
                </div>
                <div class="cal-hoja-num">
                    <span>Certificado de notas</span>
                    <strong id="cal-hoja-fecha"></strong>
                </div>
            </header>
            <div id="cal-hoja-cuerpo"></div>
        </article>
    </div>
</div>

<div class="cal-toast" id="cal-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
var CAL_DATA = {
    grupos: @json($grupos->values()),
    estudiantes: @json($estudiantes->values()),
    hoy: @json($hoy->toDateString()),
    ciudad: @json($sede?->ciudad)
};
</script>
<script src="{{ asset('assets/js/admin/calificaciones.js') }}?v={{ filemtime(public_path('assets/js/admin/calificaciones.js')) }}"></script>
@endpush
