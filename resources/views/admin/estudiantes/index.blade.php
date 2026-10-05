{{-- mock: estudiantes, acudientes, documentos, teléfonos, correos, pagos y asistencia son datos de ejemplo ficticios.
     Los grupos coinciden con los de Grupos y Horarios (mismos códigos, docentes, cupos y horarios). --}}
@extends('admin.layout')

@section('title', 'Estudiantes')
@section('page-title', 'Estudiantes')
@section('active', 'estudiantes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/estudiantes.css') }}?v={{ filemtime(public_path('assets/css/admin/estudiantes.css')) }}">
@endpush

@php
/* ── Grupos de la sede (mismos de Grupos / Horarios) ── */
$grupos = [
    ['id'=>1,  'codigo'=>'CUR-SAL-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud','docente'=>'María González Ruiz','jornada'=>'Mañana','horario'=>['Lun · Mié · Vie  08:00 – 12:00'],'cupo'=>25,'inscritos'=>22,'estado'=>'activo'],
    ['id'=>2,  'codigo'=>'CUR-SAL-001-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud','docente'=>'Alejandro Ríos Mora','jornada'=>'Tarde','horario'=>['Mar · Jue  14:00 – 18:00'],'cupo'=>25,'inscritos'=>18,'estado'=>'activo'],
    ['id'=>3,  'codigo'=>'CUR-SAL-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auxiliar de Psiquiatría','escuela'=>'Salud','docente'=>'María González Ruiz','jornada'=>'Tarde','horario'=>['Lun · Mié · Vie  14:00 – 18:00'],'cupo'=>20,'inscritos'=>19,'estado'=>'activo'],
    ['id'=>4,  'codigo'=>'CUR-TUR-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo','docente'=>'Laura Martínez Peña','jornada'=>'Mañana','horario'=>['Lun · Mar  07:00 – 11:00','Jue  14:00 – 17:00'],'cupo'=>20,'inscritos'=>20,'estado'=>'activo'],
    ['id'=>5,  'codigo'=>'CUR-TUR-001-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo','docente'=>'Andrés Zapata Villa','jornada'=>'Tarde','horario'=>['Lun · Mié · Vie  13:00 – 17:00'],'cupo'=>20,'inscritos'=>17,'estado'=>'activo'],
    ['id'=>6,  'codigo'=>'CUR-TUR-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Sommelier y Enología','escuela'=>'Cocina y Turismo','docente'=>'Andrés Zapata Villa','jornada'=>'Noche','horario'=>['Mar · Jue  18:00 – 21:00','Sáb  08:00 – 12:00'],'cupo'=>15,'inscritos'=>12,'estado'=>'activo'],
    ['id'=>7,  'codigo'=>'CUR-ADM-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas','escuela'=>'Administrativa','docente'=>'Patricia López Castro','jornada'=>'Mañana','horario'=>['Lun · Mié · Vie  08:00 – 12:00'],'cupo'=>30,'inscritos'=>28,'estado'=>'activo'],
    ['id'=>8,  'codigo'=>'CUR-ADM-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa','docente'=>'Roberto Díaz Sierra','jornada'=>'Tarde','horario'=>['Mar  14:00 – 18:00','Jue  18:00 – 21:00'],'cupo'=>25,'inscritos'=>21,'estado'=>'activo'],
    ['id'=>9,  'codigo'=>'CUR-EDU-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Asistente de Preescolar','escuela'=>'Educación e Idiomas','docente'=>'Diana Vargas Nieto','jornada'=>'Mañana','horario'=>['Lun a Vie  07:00 – 10:00'],'cupo'=>20,'inscritos'=>16,'estado'=>'activo'],
    ['id'=>10, 'codigo'=>'CUR-SAL-003-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Seguridad Ocupacional y Laboral','escuela'=>'Salud','docente'=>'Alejandro Ríos Mora','jornada'=>'Noche','horario'=>['Lun · Mié  18:00 – 22:00','Sáb  07:00 – 11:00'],'cupo'=>20,'inscritos'=>8,'estado'=>'planificacion'],
    ['id'=>11, 'codigo'=>'CUR-ADM-002-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa','docente'=>'Patricia López Castro','jornada'=>'Mañana','horario'=>['Finalizado en junio de 2026'],'cupo'=>25,'inscritos'=>25,'estado'=>'finalizado'],
];

/* ── Acudientes registrados (ficticios) ── */
$acudientes = [
    ['id'=>1,'nombres'=>'Gloria Inés','apellidos'=>'Cárdenas Mora','documento'=>'39645218','telefono'=>'3125550141','email'=>'gloria.cardenas@example.com'],
    ['id'=>2,'nombres'=>'Hernán','apellidos'=>'Pérez Gutiérrez','documento'=>'79884310','telefono'=>'3105550187','email'=>'hernan.perez@example.com'],
    ['id'=>3,'nombres'=>'Rosalba','apellidos'=>'Ospina de Vargas','documento'=>'20554936','telefono'=>'3145550122','email'=>''],
    ['id'=>4,'nombres'=>'Claudia','apellidos'=>'Martínez Gil','documento'=>'52310477','telefono'=>'3205550163','email'=>'claudia.martinez@example.com'],
];

/* ── Estudiantes (ficticios). La edad nunca se guarda: se calcula de la fecha de nacimiento. ── */
$e = fn($id,$nom,$ape,$tipo,$doc,$nac,$gen,$grupo,$estado,$extra=[]) => array_merge([
    'id'=>$id,'codigo'=>'EST-2026-'.str_pad((string)(100+$id*7),4,'0',STR_PAD_LEFT),
    'nombres'=>$nom,'apellidos'=>$ape,'tipo_doc'=>$tipo,'documento'=>$doc,'fecha_nac'=>$nac,'genero'=>$gen,
    'telefono'=>'31'.(($id*3)%10).'555'.str_pad((string)(100+$id),4,'0',STR_PAD_LEFT),
    'email'=>strtolower(\Illuminate\Support\Str::ascii(explode(' ',$nom)[0].'.'.explode(' ',$ape)[0])).'@example.com',
    'direccion'=>'Cra '.(($id*5)%30+2).' # '.(($id*3)%20+4).'-'.(($id*11)%60+10),
    'departamento'=>'Cundinamarca','ciudad'=>['Villeta','Sasaima','Guaduas','La Vega','Facatativá','Albán'][$id%6],
    'grupo_id'=>$grupo,'estado'=>$estado,'estado_fecha'=>null,'estado_motivo'=>null,
    'fecha_ingreso'=>'2026-02-02','acudiente_id'=>null,'parentesco'=>null,
    'pago'=>'al_dia','cuotas_pagadas'=>8,'cuotas_total'=>10,'saldo'=>0,'ultimo_pago'=>'2026-09-05',
    'asistencia'=>92,'documentos_pendientes'=>[],
], $extra);

$estudiantes = [
    $e(1,'Valentina','Ríos Cárdenas','TI','1031456782','2009-03-14','Femenino',1,'activo',['acudiente_id'=>1,'parentesco'=>'Madre','asistencia'=>96]),
    $e(2,'Juan Sebastián','Gómez Patiño','CC','1070958341','2001-07-22','Masculino',1,'activo',['pago'=>'pendiente','cuotas_pagadas'=>7,'saldo'=>210000,'ultimo_pago'=>'2026-08-04','asistencia'=>88]),
    $e(3,'Luisa Fernanda','Castro Mejía','CC','1072645903','1998-11-03','Femenino',1,'activo',['pago'=>'vencido','cuotas_pagadas'=>6,'saldo'=>420000,'ultimo_pago'=>'2026-07-06','asistencia'=>91]),
    $e(4,'Camilo Andrés','Herrera Vélez','TI','1031512096','2009-09-30','Masculino',2,'activo',['pago'=>'pendiente','cuotas_pagadas'=>7,'saldo'=>210000,'ultimo_pago'=>'2026-08-08','asistencia'=>79]),
    $e(5,'Daniela','Moreno Suárez','CC','1069235117','1995-02-17','Femenino',2,'aplazado',['estado_fecha'=>'2026-08-12','estado_motivo'=>'Viaje laboral por tres meses; retoma en el siguiente periodo.','pago'=>'pendiente','cuotas_pagadas'=>6,'saldo'=>210000,'ultimo_pago'=>'2026-07-02','asistencia'=>70]),
    $e(6,'Brayan Steven','Quintero Rojas','CC','1073520448','2003-05-09','Masculino',3,'activo',['asistencia'=>94]),
    $e(7,'Paula Andrea','Salazar Torres','CC','52987341','1988-12-01','Femenino',3,'activo',['asistencia'=>98]),
    $e(8,'Santiago','Pérez Londoño','TI','1032087654','2010-01-25','Masculino',4,'activo',['acudiente_id'=>2,'parentesco'=>'Padre','pago'=>'vencido','cuotas_pagadas'=>6,'saldo'=>420000,'ultimo_pago'=>'2026-07-10','asistencia'=>85]),
    $e(9,'María José','Rincón Beltrán','CC','1071330902','2000-08-14','Femenino',4,'activo',['documentos_pendientes'=>['Certificado de estudios'],'asistencia'=>92]),
    $e(10,'Kevin Alexis','Díaz Moreno','CC','1070884215','2004-04-02','Masculino',5,'retirado',['estado_fecha'=>'2026-07-03','estado_motivo'=>'Cambio de ciudad de residencia.','cuotas_pagadas'=>5,'ultimo_pago'=>'2026-06-04','asistencia'=>60]),
    $e(11,'Natalia','Vargas Ospina','TI','1031904377','2009-06-11','Femenino',5,'activo',['acudiente_id'=>3,'parentesco'=>'Abuela','asistencia'=>90]),
    $e(12,'Andrés Felipe','Muñoz Cano','CC','1072118560','1997-10-19','Masculino',6,'activo',['pago'=>'pendiente','cuotas_pagadas'=>7,'saldo'=>210000,'ultimo_pago'=>'2026-08-06','asistencia'=>87]),
    $e(13,'Laura Camila','Pineda Rojas','CC','1069774230','1999-03-27','Femenino',6,'activo',['asistencia'=>95]),
    $e(14,'Jorge Eliécer','Bautista León','CC','80456912','1979-09-05','Masculino',7,'activo',['pago'=>'vencido','cuotas_pagadas'=>5,'saldo'=>630000,'ultimo_pago'=>'2026-06-03','asistencia'=>83]),
    $e(15,'Diana Marcela','Acosta Ruiz','CC','1071009884','1996-01-30','Femenino',7,'activo',['documentos_pendientes'=>['Copia del documento de identidad','Certificado de afiliación a salud'],'asistencia'=>97]),
    $e(16,'Sergio Alejandro','Niño Parra','TI','1032245618','2010-11-08','Masculino',7,'activo',['pago'=>'pendiente','cuotas_pagadas'=>7,'saldo'=>210000,'ultimo_pago'=>'2026-08-12','asistencia'=>74]),
    $e(17,'Yuliana','Restrepo Galeano','CC','1073661025','2002-12-12','Femenino',8,'activo',['asistencia'=>93]),
    $e(18,'Cristian David','Ortiz Sánchez','CC','1070312779','2001-02-28','Masculino',8,'aplazado',['estado_fecha'=>'2026-09-01','estado_motivo'=>'Incapacidad médica de 60 días.','asistencia'=>81]),
    $e(19,'Karen Lizeth','Gil Martínez','TI','1031778403','2009-08-19','Femenino',9,'activo',['acudiente_id'=>4,'parentesco'=>'Tía','asistencia'=>99]),
    $e(20,'Mónica Patricia','Cárdenas Vela','CC','39567204','1985-06-23','Femenino',9,'activo',['pago'=>'vencido','cuotas_pagadas'=>6,'saldo'=>420000,'ultimo_pago'=>'2026-07-08','asistencia'=>89]),
    $e(21,'Edwin Fabián','Rodríguez Peña','CC','1072901336','1994-04-15','Masculino',10,'activo',['fecha_ingreso'=>'2026-09-21','cuotas_pagadas'=>1,'ultimo_pago'=>'2026-09-21','asistencia'=>null,'documentos_pendientes'=>['Certificado de estudios']]),
    $e(22,'Luz Ángela','Mendoza Soto','CC','1069880147','1990-10-10','Femenino',11,'graduado',['estado_fecha'=>'2026-06-20','fecha_ingreso'=>'2025-02-03','cuotas_pagadas'=>10,'ultimo_pago'=>'2026-05-04','asistencia'=>95]),
    $e(23,'Óscar Iván','Téllez Ramírez','CC','1071445209','1993-07-07','Masculino',11,'graduado',['estado_fecha'=>'2026-06-20','fecha_ingreso'=>'2025-02-03','cuotas_pagadas'=>10,'ultimo_pago'=>'2026-05-06','asistencia'=>92]),
    $e(24,'Érica Johana','Ríos Cárdenas','TI','1032310995','2009-12-03','Femenino',2,'activo',['acudiente_id'=>1,'parentesco'=>'Madre','asistencia'=>94]),
];

$programas = array_values(array_unique(array_column($grupos, 'programa')));
@endphp

@section('content')
<div class="est-wrap">

    {{-- Encabezado (misma estructura que Docentes) --}}
    <div class="est-header">
        <div>
            <h2 class="est-title">Estudiantes</h2>
            <p class="est-sub">Aquí podrás inscribir estudiantes en un programa y un grupo, registrar su acudiente cuando sean menores de edad y consultar su ficha completa. Desde la ficha puedes cambiarlos de grupo, actualizar su estado o restablecer su contraseña al número de documento.</p>
        </div>
        <button class="est-btn est-btn-primary" id="est-btn-inscribir" type="button">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Inscribir estudiante
        </button>
    </div>

    {{-- KPIs (mismo formato que Docentes; valores calculados por estudiantes.js) --}}
    <div class="est-kpis">
        <div class="est-kpi-chip est-kpi-blue">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-total">0</div>
                <div class="est-kpi-lbl">Estudiantes</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-green">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-activos">0</div>
                <div class="est-kpi-lbl">Activos</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-orange">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-alertas">0</div>
                <div class="est-kpi-lbl">Requieren atención</div>
            </div>
        </div>
        <div class="est-kpi-chip est-kpi-navy">
            <div class="est-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="est-kpi-val" id="est-kpi-programas">0</div>
                <div class="est-kpi-lbl">Programas</div>
            </div>
        </div>
    </div>

    {{-- Filtros (misma fila que Docentes) --}}
    <div class="est-toolbar">
        <div class="est-filtros">
            <div class="est-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="est-search" id="est-search" type="search" placeholder="Nombre o documento..." aria-label="Buscar estudiante" autocomplete="off">
            </div>
            <div class="app-combobox" id="est-cb-programa">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los programas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-programa" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-grupo">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los grupos</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-grupo" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-estado">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los estados</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-estado" data-combobox-value>
            </div>
            <div class="app-combobox" id="est-cb-alerta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las alertas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="est-val-alerta" data-combobox-value>
            </div>
            <span class="est-count-badge" id="est-count" aria-live="polite"></span>
        </div>
        <div class="est-vista" role="group" aria-label="Vista">
            <button type="button" class="est-vista-btn is-active" data-vista="lista" aria-pressed="true" title="Ver como tabla" aria-label="Ver como tabla">
                <svg viewBox="0 0 24 24"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
            <button type="button" class="est-vista-btn" data-vista="carnes" aria-pressed="false" title="Ver como carnés" aria-label="Ver como carnés">
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2"/><line x1="14" y1="10" x2="19" y2="10"/><line x1="14" y1="14" x2="18" y2="14"/></svg>
            </button>
        </div>
    </div>

    {{-- Tabla / carnés (generados por estudiantes.js) --}}
    <div class="est-grid" id="est-grid" hidden></div>
    <div class="est-lista-wrap" id="est-lista-wrap">
        <table class="est-lista">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <th>Escuela</th>
                    <th>Programa y grupo</th>
                    <th>Estado</th>
                    <th>Alertas</th>
                    <th>Contacto</th>
                    <th><span class="est-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="est-lista-body"></tbody>
        </table>
    </div>

    <div class="est-empty" id="est-empty" hidden>
        <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c.6-1.4 1.7-2 3-2s2.4.6 3 2"/><line x1="14" y1="10" x2="19" y2="10"/><line x1="14" y1="14" x2="18" y2="14"/></svg>
        <span class="est-empty-ttl">No hay estudiantes con estos filtros</span>
        <span class="est-empty-sub">Prueba con otro nombre o documento, o quita el filtro de estado, programa o alerta.</span>
        <button type="button" class="est-btn est-btn-ghost" id="est-limpiar">Quitar filtros</button>
    </div>

    <nav class="est-pagination" id="est-pagination" aria-label="Paginación" hidden>
        <button type="button" class="est-pg-btn" id="est-pg-prev">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="est-pg-pages" id="est-pg-pages"></div>
        <button type="button" class="est-pg-btn" id="est-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </nav>

</div>

{{-- ══════════ Ficha del estudiante (panel lateral) ══════════ --}}
<div class="est-drawer" id="est-drawer" aria-hidden="true">
    <div class="est-drawer-overlay" id="est-drawer-overlay"></div>
    <aside class="est-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="est-fi-nombre">
        {{-- Cabecera (mismo formato que la ficha de Docentes) --}}
        <div class="est-drawer-head">
            <div class="est-drawer-head-left">
                <div class="est-drawer-avatar" id="est-fi-avatar" aria-hidden="true"></div>
                <div class="est-drawer-head-info">
                    <h3 class="est-drawer-title" id="est-fi-nombre"></h3>
                    <div class="est-drawer-doc" id="est-fi-doc"></div>
                    <div class="est-drawer-badges" id="est-fi-badges"></div>
                </div>
            </div>
            <button type="button" class="est-icon-btn" id="est-drawer-close" aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="est-fi-tabs" id="est-fi-tabs" role="tablist">
            <button type="button" class="est-fi-tab is-active" data-tab="datos" role="tab">General</button>
            <button type="button" class="est-fi-tab" data-tab="acudiente" role="tab">Acudiente</button>
            <button type="button" class="est-fi-tab" data-tab="grupo" role="tab">Grupo y horario</button>
            <button type="button" class="est-fi-tab" data-tab="seguimiento" role="tab">Pagos y asistencia</button>
        </div>

        {{-- Vista principal de la ficha --}}
        <div class="est-drawer-body" id="est-ficha">
            <div class="est-fi-panel" id="est-fi-panel"></div>
        </div>

        {{-- Vista de acción (cambiar grupo / estado / contraseña) dentro de la misma ficha --}}
        <div class="est-drawer-body" id="est-accion" hidden>
            <button type="button" class="est-back" id="est-accion-volver">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Volver a la ficha
            </button>
            <h3 class="est-accion-ttl" id="est-accion-ttl"></h3>
            <p class="est-accion-sub" id="est-accion-sub"></p>
            <div id="est-accion-cuerpo"></div>
            <div class="est-form-error" id="est-accion-error" role="alert" hidden></div>
        </div>

        <div class="est-drawer-foot" id="est-ficha-foot">
            <button type="button" class="est-btn est-btn-secondary" data-accion="grupo">
                <svg viewBox="0 0 24 24"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                Cambiar de grupo
            </button>
            <button type="button" class="est-btn est-btn-secondary" data-accion="estado">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Cambiar estado
            </button>
            <button type="button" class="est-btn est-btn-secondary" data-accion="clave">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Restablecer contraseña
            </button>
        </div>
        <div class="est-drawer-foot" id="est-accion-foot" hidden>
            <button type="button" class="est-btn est-btn-ghost" id="est-accion-cancelar">Cancelar</button>
            <button type="button" class="est-btn est-btn-primary" id="est-accion-confirmar">Confirmar</button>
        </div>
    </aside>
</div>

{{-- ══════════ Asistente: Inscribir estudiante ══════════ --}}
<div class="est-modal" id="est-modal" aria-hidden="true">
    <div class="est-modal-overlay" id="est-modal-overlay"></div>
    <div class="est-modal-box" role="dialog" aria-modal="true" aria-labelledby="est-modal-ttl">
        <div class="est-modal-head">
            <div>
                <h3 class="est-modal-ttl" id="est-modal-ttl">Inscribir estudiante</h3>
                <span class="est-modal-paso" id="est-modal-paso">Paso 1 de 5</span>
            </div>
            <button type="button" class="est-icon-btn" id="est-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos (mismo formato que el asistente de Docentes) --}}
        <div class="est-wsteps" id="est-pasos">
            <div class="est-wstep is-active" data-step="1" title="Paso 1: Datos personales"><div class="est-wstep-dot"></div><span>Datos personales</span></div>
            <div class="est-wstep-line"></div>
            <div class="est-wstep" data-step="2" title="Paso 2: Identificación"><div class="est-wstep-dot"></div><span>Identificación</span></div>
            <div class="est-wstep-line"></div>
            <div class="est-wstep" data-step="3" title="Paso 3: Contacto"><div class="est-wstep-dot"></div><span>Contacto</span></div>
            <div class="est-wstep-line"></div>
            <div class="est-wstep" data-step="4" title="Paso 4: Acudiente"><div class="est-wstep-dot"></div><span>Acudiente</span></div>
            <div class="est-wstep-line"></div>
            <div class="est-wstep" data-step="5" title="Paso 5: Programa y grupo"><div class="est-wstep-dot"></div><span>Programa y grupo</span></div>
        </div>

        <div class="est-modal-body">

            {{-- Paso 1: Datos personales --}}
            <section class="est-mstep is-active" data-step="1">
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-nombres">Nombres *</label>
                        <input class="est-input" id="ins-nombres" type="text" autocomplete="off" placeholder="Ej. Valentina">
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-apellidos">Apellidos *</label>
                        <input class="est-input" id="ins-apellidos" type="text" autocomplete="off" placeholder="Ej. Ríos Cárdenas">
                    </div>
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-nacimiento">Fecha de nacimiento *</label>
                        <input class="est-input" id="ins-nacimiento" type="date">
                        <span class="est-edad" id="ins-edad" aria-live="polite"></span>
                    </div>
                    <div class="est-field">
                        <span class="est-lbl">Género *</span>
                        <div class="app-combobox" id="ins-cb-genero">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-genero" data-combobox-value>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Paso 2: Identificación --}}
            <section class="est-mstep" data-step="2">
                <div class="est-form-row">
                    <div class="est-field">
                        <span class="est-lbl">Tipo de documento *</span>
                        <div class="app-combobox" id="ins-cb-tipo">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-tipo" data-combobox-value>
                        </div>
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-documento">Número de documento *</label>
                        <input class="est-input est-num" id="ins-documento" type="text" inputmode="numeric" autocomplete="off" placeholder="Solo números">
                    </div>
                </div>
                <div class="est-nota">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p>El número de documento será el usuario y la contraseña inicial del estudiante. Podrá cambiarla al ingresar por primera vez.</p>
                </div>
            </section>

            {{-- Paso 3: Contacto --}}
            <section class="est-mstep" data-step="3">
                <div class="est-form-row">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-telefono">Celular *</label>
                        <input class="est-input est-num" id="ins-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                    </div>
                    <div class="est-field">
                        <label class="est-lbl" for="ins-email">Correo electrónico <em>opcional</em></label>
                        <input class="est-input" id="ins-email" type="email" autocomplete="off" placeholder="nombre@correo.com">
                    </div>
                </div>
                <div class="est-field">
                    <label class="est-lbl" for="ins-direccion">Dirección de residencia *</label>
                    <input class="est-input" id="ins-direccion" type="text" autocomplete="off" placeholder="Ej. Cra 5 # 8-20">
                </div>
                <div class="est-form-row">
                    <div class="est-field">
                        <span class="est-lbl">Departamento *</span>
                        <div class="app-combobox" id="ins-cb-depto">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Selecciona un departamento</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar departamento" autocomplete="off">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-depto" data-combobox-value>
                        </div>
                    </div>
                    <div class="est-field">
                        <span class="est-lbl">Ciudad o municipio *</span>
                        <div class="app-combobox" id="ins-cb-ciudad">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Elige el departamento primero</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar municipio" autocomplete="off">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="ins-ciudad" data-combobox-value>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Paso 4: Acudiente --}}
            <section class="est-mstep" data-step="4">
                <div class="est-acu-aviso" id="ins-acu-aviso"></div>

                <label class="est-check" id="ins-acu-opcional">
                    <input type="checkbox" id="ins-acu-agregar">
                    <span>Registrar un acudiente para este estudiante</span>
                </label>

                <div id="ins-acu-bloque">
                    <div class="est-field">
                        <label class="est-lbl" for="ins-acu-buscar">Cédula del acudiente *</label>
                        <div class="est-buscar-row">
                            <input class="est-input est-num" id="ins-acu-buscar" type="text" inputmode="numeric" autocomplete="off" placeholder="Busca primero si ya está registrado">
                            <button type="button" class="est-btn est-btn-navy" id="ins-acu-btn-buscar">Buscar</button>
                        </div>
                    </div>

                    <div id="ins-acu-resultado"></div>

                    <div id="ins-acu-nuevo" hidden>
                        <div class="est-form-row">
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-nombres">Nombres *</label>
                                <input class="est-input" id="ins-acu-nombres" type="text" autocomplete="off">
                            </div>
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-apellidos">Apellidos *</label>
                                <input class="est-input" id="ins-acu-apellidos" type="text" autocomplete="off">
                            </div>
                        </div>
                        <div class="est-form-row">
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-telefono">Celular *</label>
                                <input class="est-input est-num" id="ins-acu-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                            </div>
                            <div class="est-field">
                                <label class="est-lbl" for="ins-acu-email">Correo <em>opcional</em></label>
                                <input class="est-input" id="ins-acu-email" type="email" autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <div class="est-field" id="ins-acu-parentesco-wrap" hidden>
                        <span class="est-lbl">Parentesco con el estudiante *</span>
                        <div class="est-chips" id="ins-parentesco" role="radiogroup" aria-label="Parentesco"></div>
                    </div>
                </div>
            </section>

            {{-- Paso 5: Programa y grupo --}}
            <section class="est-mstep" data-step="5">
                <div class="est-field">
                    <span class="est-lbl">Programa *</span>
                    <div class="app-combobox" id="ins-cb-programa">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Selecciona el programa</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="ins-programa" data-combobox-value>
                    </div>
                </div>
                <div class="est-field">
                    <span class="est-lbl">Grupo *</span>
                    <div class="est-grupos-opc" id="ins-grupos" role="radiogroup" aria-label="Grupo"></div>
                </div>
                <div class="est-resumen" id="ins-resumen" hidden></div>
            </section>

            <div class="est-form-error" id="ins-error" role="alert" hidden></div>
        </div>

        <div class="est-modal-foot">
            <button type="button" class="est-btn est-btn-ghost" id="est-btn-atras">Atrás</button>
            <button type="button" class="est-btn est-btn-primary" id="est-btn-siguiente">Siguiente</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="est-toast" id="est-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var EST_DATA = @json($estudiantes);
    var EST_GRUPOS = @json($grupos);
    var EST_ACUDIENTES = @json($acudientes);
</script>
<script src="{{ asset('assets/js/admin/estudiantes.js') }}?v={{ filemtime(public_path('assets/js/admin/estudiantes.js')) }}" defer></script>
@endpush
