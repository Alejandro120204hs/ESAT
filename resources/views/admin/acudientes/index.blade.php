{{-- mock: acudientes, estudiantes, documentos, teléfonos, correos, direcciones, pagos, asistencia e ingresos son datos de ejemplo ficticios.
     Los grupos y estudiantes coinciden con los de Estudiantes y Grupos y Horarios (mismos códigos, nombres y vínculos).
     Falta backend: migración de acudientes + tabla pivote acudiente_estudiante (parentesco), controlador, validación y cuentas de usuario rol acudiente. --}}
@extends('admin.layout')

@section('title', 'Acudientes')
@section('page-title', 'Acudientes')
@section('active', 'acudientes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/acudientes.css') }}?v={{ filemtime(public_path('assets/css/admin/acudientes.css')) }}">
@endpush

@php
/* ── Grupos de la sede (mismos de Grupos / Horarios y Estudiantes) ── */
$grupos = [
    ['id'=>1,  'codigo'=>'CUR-SAL-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud','jornada'=>'Mañana','estado'=>'activo'],
    ['id'=>2,  'codigo'=>'CUR-SAL-001-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Servicios Geriátricos','escuela'=>'Salud','jornada'=>'Tarde','estado'=>'activo'],
    ['id'=>3,  'codigo'=>'CUR-SAL-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auxiliar de Psiquiatría','escuela'=>'Salud','jornada'=>'Tarde','estado'=>'activo'],
    ['id'=>4,  'codigo'=>'CUR-TUR-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo','jornada'=>'Mañana','estado'=>'activo'],
    ['id'=>5,  'codigo'=>'CUR-TUR-001-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Cocina Nacional e Internacional','escuela'=>'Cocina y Turismo','jornada'=>'Tarde','estado'=>'activo'],
    ['id'=>6,  'codigo'=>'CUR-TUR-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Sommelier y Enología','escuela'=>'Cocina y Turismo','jornada'=>'Noche','estado'=>'activo'],
    ['id'=>7,  'codigo'=>'CUR-ADM-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas','escuela'=>'Administrativa','jornada'=>'Mañana','estado'=>'activo'],
    ['id'=>8,  'codigo'=>'CUR-ADM-002-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa','jornada'=>'Tarde','estado'=>'activo'],
    ['id'=>9,  'codigo'=>'CUR-EDU-001-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Asistente de Preescolar','escuela'=>'Educación e Idiomas','jornada'=>'Mañana','estado'=>'activo'],
    ['id'=>10, 'codigo'=>'CUR-SAL-003-A','grupo'=>'Grupo A','programa'=>'Técnico Laboral en Seguridad Ocupacional y Laboral','escuela'=>'Salud','jornada'=>'Noche','estado'=>'planificacion'],
    ['id'=>11, 'codigo'=>'CUR-ADM-002-B','grupo'=>'Grupo B','programa'=>'Técnico Laboral en Auxiliar Contable y Administrativo','escuela'=>'Administrativa','jornada'=>'Mañana','estado'=>'finalizado'],
];

/* ── Acudientes (ficticios). cuenta: activa | sin_ingreso (nunca ha entrado al portal) | inactiva ── */
$a = fn($id,$nom,$ape,$doc,$tel,$email,$ciudad,$ocup,$cuenta,$ingreso,$registro) => [
    'id'=>$id,'nombres'=>$nom,'apellidos'=>$ape,'tipo_doc'=>'CC','documento'=>$doc,'telefono'=>$tel,'email'=>$email,
    'direccion'=>'Calle '.(($id*4)%25+3).' # '.(($id*7)%18+2).'-'.(($id*13)%70+11),
    'departamento'=>'Cundinamarca','ciudad'=>$ciudad,'ocupacion'=>$ocup,
    'cuenta'=>$cuenta,'ultimo_ingreso'=>$ingreso,'fecha_registro'=>$registro,
];
$acudientes = [
    $a(1, 'Gloria Inés','Cárdenas Mora','39645218','3125550141','gloria.cardenas@example.com','Villeta','Comerciante','activa','2026-10-03','2025-02-03'),
    $a(2, 'Hernán','Pérez Gutiérrez','79884310','3105550187','hernan.perez@example.com','Guaduas','Conductor','activa','2026-09-18','2026-02-02'),
    $a(3, 'Rosalba','Ospina de Vargas','20554936','3145550122','','Sasaima','Pensionada','sin_ingreso',null,'2026-02-02'),
    $a(4, 'Claudia','Martínez Gil','52310477','3205550163','claudia.martinez@example.com','Villeta','Docente','activa','2026-10-05','2026-02-02'),
    $a(5, 'Martha Lucía','Quintero Rojas','35412987','3115550109','martha.quintero@example.com','La Vega','Auxiliar de enfermería','activa','2026-09-29','2026-02-02'),
    $a(6, 'Luis Alberto','Díaz Ruiz','80234561','3165550178','','Facatativá','Agricultor','inactiva','2026-06-30','2026-02-02'),
    $a(7, 'Ana Milena','Restrepo Galeano','1070456223','3005550134','ana.restrepo@example.com','Albán','Estilista','activa','2026-09-10','2026-02-02'),
    $a(8, 'Jairo','Ortiz Cárdenas','79556120','3135550156','jairo.ortiz@example.com','Villeta','Mecánico','activa','2026-08-27','2026-02-02'),
    $a(9, 'Nelly','Gómez Patiño','39782045','3185550117','nelly.gomez@example.com','Sasaima','Ama de casa','sin_ingreso',null,'2026-02-02'),
    $a(10,'Fabio','Herrera Salcedo','80765432','3215550190','fabio.herrera@example.com','Guaduas','Contador','activa','2026-07-14','2026-02-02'),
    $a(11,'Blanca Cecilia','Rincón de Beltrán','41687320','3175550125','','La Vega','Modista','activa','2026-09-02','2026-02-02'),
    $a(12,'Diego Fernando','Pineda Rojas','1069552781','3045550148','diego.pineda@example.com','Facatativá','Técnico electricista','activa','2026-10-01','2026-02-02'),
    $a(13,'Teresa','Mendoza Soto','20887612','3195550103','teresa.mendoza@example.com','Albán','Pensionada','activa','2026-06-21','2025-02-03'),
    $a(14,'Orlando','Castro Mejía','79310845','3155550172','orlando.castro@example.com','Villeta','Ganadero','activa','2026-08-15','2026-02-02'),
];

/* ── Estudiantes (los mismos de la pantalla Estudiantes, con los campos que usa esta vista) ── */
$e = fn($id,$nom,$ape,$tipo,$doc,$nac,$grupo,$estado,$extra=[]) => array_merge([
    'id'=>$id,'codigo'=>'EST-2026-'.str_pad((string)(100+$id*7),4,'0',STR_PAD_LEFT),
    'nombres'=>$nom,'apellidos'=>$ape,'tipo_doc'=>$tipo,'documento'=>$doc,'fecha_nac'=>$nac,
    'grupo_id'=>$grupo,'estado'=>$estado,'acudiente_id'=>null,'parentesco'=>null,
    'pago'=>'al_dia','saldo'=>0,'asistencia'=>92,
], $extra);

$estudiantes = [
    $e(1,'Valentina','Ríos Cárdenas','TI','1031456782','2009-03-14',1,'activo',['acudiente_id'=>1,'parentesco'=>'Madre','asistencia'=>96]),
    $e(2,'Juan Sebastián','Gómez Patiño','CC','1070958341','2001-07-22',1,'activo',['acudiente_id'=>9,'parentesco'=>'Madre','pago'=>'pendiente','saldo'=>210000,'asistencia'=>88]),
    $e(3,'Luisa Fernanda','Castro Mejía','CC','1072645903','1998-11-03',1,'activo',['acudiente_id'=>14,'parentesco'=>'Padre','pago'=>'vencido','saldo'=>420000,'asistencia'=>91]),
    $e(4,'Camilo Andrés','Herrera Vélez','TI','1031512096','2009-09-30',2,'activo',['pago'=>'pendiente','saldo'=>210000,'asistencia'=>79]),
    $e(5,'Daniela','Moreno Suárez','CC','1069235117','1995-02-17',2,'aplazado',['pago'=>'pendiente','saldo'=>210000,'asistencia'=>70]),
    $e(6,'Brayan Steven','Quintero Rojas','CC','1073520448','2003-05-09',3,'activo',['acudiente_id'=>5,'parentesco'=>'Madre','asistencia'=>94]),
    $e(7,'Paula Andrea','Salazar Torres','CC','52987341','1988-12-01',3,'activo',['asistencia'=>98]),
    $e(8,'Santiago','Pérez Londoño','TI','1032087654','2010-01-25',4,'activo',['acudiente_id'=>2,'parentesco'=>'Padre','pago'=>'vencido','saldo'=>420000,'asistencia'=>85]),
    $e(9,'María José','Rincón Beltrán','CC','1071330902','2000-08-14',4,'activo',['acudiente_id'=>11,'parentesco'=>'Madre','asistencia'=>92]),
    $e(10,'Kevin Alexis','Díaz Moreno','CC','1070884215','2004-04-02',5,'retirado',['acudiente_id'=>6,'parentesco'=>'Padre','asistencia'=>60]),
    $e(11,'Natalia','Vargas Ospina','TI','1031904377','2009-06-11',5,'activo',['acudiente_id'=>3,'parentesco'=>'Abuela','asistencia'=>90]),
    $e(12,'Andrés Felipe','Muñoz Cano','CC','1072118560','1997-10-19',6,'activo',['pago'=>'pendiente','saldo'=>210000,'asistencia'=>87]),
    $e(13,'Laura Camila','Pineda Rojas','CC','1069774230','1999-03-27',6,'activo',['acudiente_id'=>12,'parentesco'=>'Hermano','asistencia'=>95]),
    $e(14,'Jorge Eliécer','Bautista León','CC','80456912','1979-09-05',7,'activo',['pago'=>'vencido','saldo'=>630000,'asistencia'=>83]),
    $e(15,'Diana Marcela','Acosta Ruiz','CC','1071009884','1996-01-30',7,'activo',['asistencia'=>97]),
    $e(16,'Sergio Alejandro','Niño Parra','TI','1032245618','2010-11-08',7,'activo',['pago'=>'pendiente','saldo'=>210000,'asistencia'=>74]),
    $e(17,'Yuliana','Restrepo Galeano','CC','1073661025','2002-12-12',8,'activo',['acudiente_id'=>7,'parentesco'=>'Hermana','asistencia'=>93]),
    $e(18,'Cristian David','Ortiz Sánchez','CC','1070312779','2001-02-28',8,'aplazado',['acudiente_id'=>8,'parentesco'=>'Padre','asistencia'=>81]),
    $e(19,'Karen Lizeth','Gil Martínez','TI','1031778403','2009-08-19',9,'activo',['acudiente_id'=>4,'parentesco'=>'Tía','asistencia'=>99]),
    $e(20,'Mónica Patricia','Cárdenas Vela','CC','39567204','1985-06-23',9,'activo',['pago'=>'vencido','saldo'=>420000,'asistencia'=>89]),
    $e(21,'Edwin Fabián','Rodríguez Peña','CC','1072901336','1994-04-15',10,'activo',['asistencia'=>null]),
    $e(22,'Luz Ángela','Mendoza Soto','CC','1069880147','1990-10-10',11,'graduado',['acudiente_id'=>13,'parentesco'=>'Madre','asistencia'=>95]),
    $e(23,'Óscar Iván','Téllez Ramírez','CC','1071445209','1993-07-07',11,'graduado',['asistencia'=>92]),
    $e(24,'Érica Johana','Ríos Cárdenas','TI','1032310995','2009-12-03',2,'activo',['acudiente_id'=>1,'parentesco'=>'Madre','asistencia'=>94]),
];
@endphp

@section('content')
<div class="acu-wrap">

    {{-- Encabezado (misma estructura que Docentes y Estudiantes) --}}
    <div class="acu-header">
        <div>
            <h2 class="acu-title">Acudientes</h2>
            <p class="acu-sub">Aquí podrás registrar a las personas responsables de los estudiantes, vincularlas con uno o varios estudiantes y consultar su ficha. Con su cédula ingresan al portal de acudientes para seguir notas, asistencia y pagos.</p>
        </div>
        <button class="acu-btn acu-btn-primary" id="acu-btn-nuevo" type="button">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Registrar acudiente
        </button>
    </div>

    {{-- KPIs (mismo formato que Docentes; valores calculados por acudientes.js) --}}
    <div class="acu-kpis">
        <div class="acu-kpi-chip acu-kpi-blue">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-total">0</div>
                <div class="acu-kpi-lbl">Acudientes</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-green">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-portal">0</div>
                <div class="acu-kpi-lbl">Usan el portal</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-orange">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-menores">0</div>
                <div class="acu-kpi-lbl">Menores sin acudiente</div>
            </div>
        </div>
        <div class="acu-kpi-chip acu-kpi-navy">
            <div class="acu-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            </div>
            <div>
                <div class="acu-kpi-val" id="acu-kpi-estudiantes">0</div>
                <div class="acu-kpi-lbl">Estudiantes a cargo</div>
            </div>
        </div>
    </div>

    {{-- Filtros (misma fila que Docentes) --}}
    <div class="acu-toolbar">
        <div class="acu-filtros">
            <div class="acu-search-wrap">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input class="acu-search" id="acu-search" type="search" placeholder="Acudiente, cédula o estudiante..." aria-label="Buscar acudiente" autocomplete="off">
            </div>
            <div class="app-combobox" id="acu-cb-programa">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todos los programas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-programa" data-combobox-value>
            </div>
            <div class="app-combobox" id="acu-cb-cuenta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las cuentas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-cuenta" data-combobox-value>
            </div>
            <div class="app-combobox" id="acu-cb-alerta">
                <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                    <span data-combobox-label>Todas las alertas</span>
                    <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div class="app-combobox-panel" data-combobox-panel hidden>
                    <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                </div>
                <input type="hidden" id="acu-val-alerta" data-combobox-value>
            </div>
            <span class="acu-count-badge" id="acu-count" aria-live="polite"></span>
        </div>
    </div>

    {{-- Tabla (generada por acudientes.js) --}}
    <div class="acu-lista-wrap" id="acu-lista-wrap">
        <table class="acu-lista">
            <thead>
                <tr>
                    <th>Acudiente</th>
                    <th>Estudiantes a cargo</th>
                    <th>Cuenta</th>
                    <th>Alertas</th>
                    <th>Contacto</th>
                    <th><span class="acu-sr">Acciones</span></th>
                </tr>
            </thead>
            <tbody id="acu-lista-body"></tbody>
        </table>
    </div>

    <div class="acu-empty" id="acu-empty" hidden>
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span class="acu-empty-ttl">No hay acudientes con estos filtros</span>
        <span class="acu-empty-sub">Prueba con otro nombre, cédula o estudiante, o quita el filtro de programa, cuenta o alerta.</span>
        <button type="button" class="acu-btn acu-btn-ghost" id="acu-limpiar">Quitar filtros</button>
    </div>

    <nav class="acu-pagination" id="acu-pagination" aria-label="Paginación" hidden>
        <button type="button" class="acu-pg-btn" id="acu-pg-prev">
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="acu-pg-pages" id="acu-pg-pages"></div>
        <button type="button" class="acu-pg-btn" id="acu-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </nav>

</div>

{{-- ══════════ Ficha del acudiente (panel lateral) ══════════ --}}
<div class="acu-drawer" id="acu-drawer" aria-hidden="true">
    <div class="acu-drawer-overlay" id="acu-drawer-overlay"></div>
    <aside class="acu-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="acu-fi-nombre">
        <div class="acu-drawer-head">
            <div class="acu-drawer-head-left">
                <div class="acu-drawer-avatar" id="acu-fi-avatar" aria-hidden="true"></div>
                <div class="acu-drawer-head-info">
                    <h3 class="acu-drawer-title" id="acu-fi-nombre"></h3>
                    <div class="acu-drawer-doc" id="acu-fi-doc"></div>
                    <div class="acu-drawer-badges" id="acu-fi-badges"></div>
                </div>
            </div>
            <button type="button" class="acu-icon-btn" id="acu-drawer-close" aria-label="Cerrar ficha">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="acu-fi-tabs" id="acu-fi-tabs" role="tablist">
            <button type="button" class="acu-fi-tab is-active" data-tab="datos" role="tab">General</button>
            <button type="button" class="acu-fi-tab" data-tab="estudiantes" role="tab">Estudiantes a cargo</button>
            <button type="button" class="acu-fi-tab" data-tab="cuenta" role="tab">Cuenta</button>
        </div>

        {{-- Vista principal de la ficha --}}
        <div class="acu-drawer-body" id="acu-ficha">
            <div class="acu-fi-panel" id="acu-fi-panel"></div>
        </div>

        {{-- Vista de acción (vincular / desvincular / cuenta / contraseña) dentro de la misma ficha --}}
        <div class="acu-drawer-body" id="acu-accion" hidden>
            <button type="button" class="acu-back" id="acu-accion-volver">
                <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
                Volver a la ficha
            </button>
            <h3 class="acu-accion-ttl" id="acu-accion-ttl"></h3>
            <p class="acu-accion-sub" id="acu-accion-sub"></p>
            <div id="acu-accion-cuerpo"></div>
            <div class="acu-form-error" id="acu-accion-error" role="alert" hidden></div>
        </div>

        <div class="acu-drawer-foot" id="acu-ficha-foot">
            <button type="button" class="acu-btn acu-btn-secondary" data-accion="editar">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                Editar datos
            </button>
            <button type="button" class="acu-btn acu-btn-secondary" data-accion="clave">
                <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Restablecer contraseña
            </button>
            <button type="button" class="acu-btn acu-btn-danger" data-accion="cuenta" id="acu-btn-cuenta">Desactivar cuenta</button>
        </div>
        <div class="acu-drawer-foot" id="acu-accion-foot" hidden>
            <button type="button" class="acu-btn acu-btn-ghost" id="acu-accion-cancelar">Cancelar</button>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-accion-confirmar">Confirmar</button>
        </div>
    </aside>
</div>

{{-- ══════════ Asistente: Registrar / editar acudiente ══════════ --}}
<div class="acu-modal" id="acu-modal" aria-hidden="true">
    <div class="acu-modal-overlay" id="acu-modal-overlay"></div>
    <div class="acu-modal-box" role="dialog" aria-modal="true" aria-labelledby="acu-modal-ttl">
        <div class="acu-modal-head">
            <div>
                <h3 class="acu-modal-ttl" id="acu-modal-ttl">Registrar acudiente</h3>
                <span class="acu-modal-paso" id="acu-modal-paso">Paso 1 de 3</span>
            </div>
            <button type="button" class="acu-icon-btn" id="acu-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Indicador de pasos (mismo formato que el asistente de Docentes) --}}
        <div class="acu-wsteps" id="acu-pasos">
            <div class="acu-wstep is-active" data-step="1" title="Paso 1: Datos personales"><div class="acu-wstep-dot"></div><span>Datos personales</span></div>
            <div class="acu-wstep-line"></div>
            <div class="acu-wstep" data-step="2" title="Paso 2: Contacto"><div class="acu-wstep-dot"></div><span>Contacto</span></div>
            <div class="acu-wstep-line"></div>
            <div class="acu-wstep" data-step="3" title="Paso 3: Estudiantes a cargo"><div class="acu-wstep-dot"></div><span>Estudiantes a cargo</span></div>
        </div>

        <div class="acu-modal-body">

            {{-- Paso 1: Datos personales e identificación --}}
            <section class="acu-mstep is-active" data-step="1">
                <div class="acu-form-row">
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-nombres">Nombres *</label>
                        <input class="acu-input" id="reg-nombres" type="text" autocomplete="off" placeholder="Ej. Gloria Inés">
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-apellidos">Apellidos *</label>
                        <input class="acu-input" id="reg-apellidos" type="text" autocomplete="off" placeholder="Ej. Cárdenas Mora">
                    </div>
                </div>
                <div class="acu-form-row">
                    <div class="acu-field">
                        <span class="acu-lbl">Tipo de documento *</span>
                        <div class="app-combobox" id="reg-cb-tipo">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="reg-tipo" data-combobox-value>
                        </div>
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-documento">Número de documento *</label>
                        <input class="acu-input acu-num" id="reg-documento" type="text" inputmode="numeric" autocomplete="off" placeholder="Solo números">
                    </div>
                </div>
                <div class="acu-field">
                    <label class="acu-lbl" for="reg-ocupacion">Ocupación <em>opcional</em></label>
                    <input class="acu-input" id="reg-ocupacion" type="text" autocomplete="off" placeholder="Ej. Comerciante">
                </div>
                <div class="acu-nota" id="reg-nota-clave">
                    <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p>El número de documento será el usuario y la contraseña inicial del acudiente en el portal. Podrá cambiarla al ingresar por primera vez.</p>
                </div>
            </section>

            {{-- Paso 2: Contacto --}}
            <section class="acu-mstep" data-step="2">
                <div class="acu-form-row">
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-telefono">Celular *</label>
                        <input class="acu-input acu-num" id="reg-telefono" type="tel" inputmode="numeric" autocomplete="off" placeholder="3XX XXX XXXX">
                    </div>
                    <div class="acu-field">
                        <label class="acu-lbl" for="reg-email">Correo electrónico <em>opcional</em></label>
                        <input class="acu-input" id="reg-email" type="email" autocomplete="off" placeholder="nombre@correo.com">
                    </div>
                </div>
                <div class="acu-field">
                    <label class="acu-lbl" for="reg-direccion">Dirección de residencia *</label>
                    <input class="acu-input" id="reg-direccion" type="text" autocomplete="off" placeholder="Ej. Calle 7 # 9-24">
                </div>
                <div class="acu-form-row">
                    <div class="acu-field">
                        <span class="acu-lbl">Departamento *</span>
                        <div class="app-combobox" id="reg-cb-depto">
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
                            <input type="hidden" id="reg-depto" data-combobox-value>
                        </div>
                    </div>
                    <div class="acu-field">
                        <span class="acu-lbl">Ciudad o municipio *</span>
                        <div class="app-combobox" id="reg-cb-ciudad">
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
                            <input type="hidden" id="reg-ciudad" data-combobox-value>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Paso 3: Estudiantes a cargo --}}
            <section class="acu-mstep" data-step="3">
                <div class="acu-field">
                    <span class="acu-lbl">Estudiante *</span>
                    <div class="app-combobox" id="reg-cb-estudiante">
                        <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                            <span data-combobox-label>Busca por nombre o documento</span>
                            <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div class="app-combobox-panel" data-combobox-panel hidden>
                            <div class="app-combobox-search-wrap">
                                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" class="app-combobox-search" data-combobox-search placeholder="Nombre o documento" autocomplete="off">
                            </div>
                            <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                        </div>
                        <input type="hidden" id="reg-estudiante" data-combobox-value>
                    </div>
                </div>
                <div class="acu-field" id="reg-par-wrap" hidden>
                    <span class="acu-lbl">Parentesco con el estudiante *</span>
                    <div class="acu-chips" id="reg-parentesco" role="radiogroup" aria-label="Parentesco"></div>
                </div>
                <div id="reg-reemplazo"></div>
                <div class="acu-agregar-row">
                    <button type="button" class="acu-btn acu-btn-navy acu-btn-sm" id="reg-agregar">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Agregar estudiante
                    </button>
                </div>

                <div class="acu-sec">
                    <div class="acu-sec-head">
                        <h4 class="acu-sec-title">Estudiantes vinculados</h4>
                        <span class="acu-sec-dato" id="reg-vinc-count">0</span>
                    </div>
                    <ul class="acu-vinculos" id="reg-vinculos"></ul>
                </div>

                <div class="acu-resumen" id="reg-resumen" hidden></div>
            </section>

            <div class="acu-form-error" id="reg-error" role="alert" hidden></div>
        </div>

        <div class="acu-modal-foot">
            <button type="button" class="acu-btn acu-btn-ghost" id="acu-btn-atras">Atrás</button>
            <button type="button" class="acu-btn acu-btn-primary" id="acu-btn-siguiente">Siguiente</button>
        </div>
    </div>
</div>

{{-- Aviso al guardar --}}
<div class="acu-toast" id="acu-toast" role="status" aria-live="polite" hidden></div>
@endsection

@push('scripts')
<script>
    var ACU_DATA = @json($acudientes);
    var ACU_ESTUDIANTES = @json($estudiantes);
    var ACU_GRUPOS = @json($grupos);
</script>
<script src="{{ asset('assets/js/admin/acudientes.js') }}?v={{ filemtime(public_path('assets/js/admin/acudientes.js')) }}" defer></script>
@endpush
