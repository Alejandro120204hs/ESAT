{{-- mock --}}
@extends('admin.layout')

@section('title', 'Programas')
@section('page-title', 'Programas')
@section('active', 'programas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/programas.css') }}">
@endpush

@php
$sedeName = auth()->user()->sede?->nombre ?? 'Sede';
$escMap = [
    'Salud'              => 'sal',
    'Cocina y Turismo'   => 'tur',
    'Administrativa'     => 'adm',
    'Educación e Idiomas'=> 'edu',
    'Deporte y Cultura'  => 'dep',
    'Ciencias'           => 'cie',
    'Belleza'            => 'bel',
];

/* ---- mock data ---- */
$programas = [
    [
        'id'=>1,'codigo'=>'ESA-SAL-001',
        'nombre'=>'Técnico Laboral en Servicios Geriátricos',
        'escuela'=>'Salud','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1800,'meses'=>12,'resolucion'=>'Res. 4521-2022 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde'],'cupo'=>25,'matricula'=>350000,'valor_programa'=>2030000,'valor_mensual'=>140000,
        'estudiantes'=>45,'estado'=>'activo',
        'descripcion'=>'Forma técnicos laborales con competencias para la atención integral del adulto mayor en instituciones geriátricas, hogares y residencias de cuidado.',
        'perfil_egreso'=>'Atender con calidad al adulto mayor, aplicar protocolos de cuidado básico y apoyar al equipo interdisciplinario de salud.',
        'modulos'=>[
            ['nombre'=>'Fundamentos de gerontología','horas'=>160,'docente'=>'Dra. Sandra Moreno'],
            ['nombre'=>'Cuidado básico de enfermería','horas'=>240,'docente'=>'Enf. Lucía Vargas'],
            ['nombre'=>'Nutrición en el adulto mayor','horas'=>120,'docente'=>'Nutr. Carlos Díaz'],
            ['nombre'=>'Actividades terapéuticas','horas'=>160,'docente'=>'T.O. María Suárez'],
            ['nombre'=>'Ética y legislación en salud','horas'=>80,'docente'=>'Dr. Andrés Gil'],
            ['nombre'=>'Primeros auxilios','horas'=>80,'docente'=>'Enf. Lucía Vargas'],
            ['nombre'=>'Práctica clínica','horas'=>960,'docente'=>'Coordinación clínica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>25,'inscritos'=>22,'inicio'=>'03 Feb 2026','fin'=>'30 Ene 2027'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>25,'inscritos'=>23,'inicio'=>'03 Feb 2026','fin'=>'30 Ene 2027'],
        ],
    ],
    [
        'id'=>2,'codigo'=>'ESA-SAL-002',
        'nombre'=>'Técnico Laboral en Auxiliar de Psiquiatría',
        'escuela'=>'Salud','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1800,'meses'=>12,'resolucion'=>'Res. 3876-2021 · SED Bogotá',
        'jornadas'=>['Mañana','Noche'],'cupo'=>20,'matricula'=>380000,'valor_programa'=>2120000,'valor_mensual'=>145000,
        'estudiantes'=>38,'estado'=>'activo',
        'descripcion'=>'Capacita al estudiante para asistir al equipo de salud mental en la atención a personas con trastornos psiquiátricos en clínicas y hospitales.',
        'perfil_egreso'=>'Brindar atención básica a pacientes psiquiátricos, aplicar técnicas de contención y apoyo terapéutico bajo supervisión profesional.',
        'modulos'=>[
            ['nombre'=>'Introducción a la salud mental','horas'=>160,'docente'=>'Psic. Ana Torres'],
            ['nombre'=>'Psicopatología básica','horas'=>200,'docente'=>'Dr. Rodrigo Muñoz'],
            ['nombre'=>'Cuidado al paciente psiquiátrico','horas'=>240,'docente'=>'Enf. Beatriz Lara'],
            ['nombre'=>'Farmacología en psiquiatría','horas'=>80,'docente'=>'Dr. Rodrigo Muñoz'],
            ['nombre'=>'Ética y derechos del paciente','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica clínica','horas'=>1040,'docente'=>'Coordinación clínica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>20,'inscritos'=>19,'inicio'=>'01 Mar 2026','fin'=>'28 Feb 2027'],
            ['nombre'=>'Grupo B — Noche','jornada'=>'Noche','cupo'=>20,'inscritos'=>19,'inicio'=>'01 Mar 2026','fin'=>'28 Feb 2027'],
        ],
    ],
    [
        'id'=>3,'codigo'=>'ESA-TUR-001',
        'nombre'=>'Técnico Laboral en Cocina Nacional e Internacional',
        'escuela'=>'Cocina y Turismo','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1600,'meses'=>10,'resolucion'=>'Res. 2210-2023 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde','Noche'],'cupo'=>20,'matricula'=>420000,'valor_programa'=>2020000,'valor_mensual'=>160000,
        'estudiantes'=>52,'estado'=>'activo',
        'descripcion'=>'Desarrolla habilidades culinarias en gastronomía nacional e internacional, manejo de cocina profesional, higiene alimentaria y montaje de platos.',
        'perfil_egreso'=>'Elaborar preparaciones culinarias nacionales e internacionales, gestionar procesos de cocina y aplicar normas de higiene y seguridad alimentaria.',
        'modulos'=>[
            ['nombre'=>'Técnicas básicas de cocina','horas'=>200,'docente'=>'Chef Mauricio Ríos'],
            ['nombre'=>'Gastronomía colombiana','horas'=>160,'docente'=>'Chef Mauricio Ríos'],
            ['nombre'=>'Cocina internacional','horas'=>200,'docente'=>'Chef Isabel Peralta'],
            ['nombre'=>'Panadería y repostería','horas'=>160,'docente'=>'Chef Isabel Peralta'],
            ['nombre'=>'Higiene y seguridad alimentaria','horas'=>80,'docente'=>'Ing. Claudia Mesa'],
            ['nombre'=>'Práctica en cocina profesional','horas'=>800,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>20,'inscritos'=>18,'inicio'=>'13 Ene 2026','fin'=>'10 Nov 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>20,'inscritos'=>18,'inicio'=>'13 Ene 2026','fin'=>'10 Nov 2026'],
            ['nombre'=>'Grupo C — Noche','jornada'=>'Noche','cupo'=>20,'inscritos'=>16,'inicio'=>'13 Ene 2026','fin'=>'10 Nov 2026'],
        ],
    ],
    [
        'id'=>4,'codigo'=>'ESA-ADM-001',
        'nombre'=>'Técnico Laboral en Auditoría y Facturación de Cuentas Médicas',
        'escuela'=>'Administrativa','nivel'=>'Técnico Laboral','modalidad'=>'Mixta',
        'horas'=>1600,'meses'=>10,'resolucion'=>'Res. 1895-2020 · SED Bogotá',
        'jornadas'=>['Tarde','Fines de semana'],'cupo'=>25,'matricula'=>300000,'valor_programa'=>1600000,'valor_mensual'=>130000,
        'estudiantes'=>29,'estado'=>'activo',
        'descripcion'=>'Forma técnicos en codificación y auditoría de cuentas médicas para el sistema de salud colombiano, con énfasis en SOAT, EPS e IPS.',
        'perfil_egreso'=>'Codificar y auditar cuentas médicas según la normativa colombiana, gestionar procesos de facturación en IPS, EPS y aseguradoras.',
        'modulos'=>[
            ['nombre'=>'Sistema de salud colombiano','horas'=>120,'docente'=>'Adm. Rosa Jiménez'],
            ['nombre'=>'Codificación CIE-10 y CUPS','horas'=>200,'docente'=>'Adm. Rosa Jiménez'],
            ['nombre'=>'Facturación y glosas','horas'=>160,'docente'=>'Cont. Hernán Ospina'],
            ['nombre'=>'Auditoría de cuentas médicas','horas'=>160,'docente'=>'Dr. Víctor Ángel'],
            ['nombre'=>'Normativa SOAT y PGSS','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica supervisada','horas'=>880,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Tarde','jornada'=>'Tarde','cupo'=>25,'inscritos'=>14,'inicio'=>'16 Feb 2026','fin'=>'11 Dic 2026'],
            ['nombre'=>'Grupo B — Fines de semana','jornada'=>'Fines de semana','cupo'=>25,'inscritos'=>15,'inicio'=>'21 Feb 2026','fin'=>'19 Dic 2026'],
        ],
    ],
    [
        'id'=>5,'codigo'=>'ESA-ADM-002',
        'nombre'=>'Técnico Laboral en Auxiliar Contable y Administrativo',
        'escuela'=>'Administrativa','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1440,'meses'=>9,'resolucion'=>'Res. 3102-2019 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde','Noche'],'cupo'=>25,'matricula'=>280000,'valor_programa'=>1288000,'valor_mensual'=>112000,
        'estudiantes'=>41,'estado'=>'activo',
        'descripcion'=>'Forma técnicos para apoyar procesos contables y administrativos en empresas, manejando herramientas ofimáticas, nómina, facturación y contabilidad básica.',
        'perfil_egreso'=>'Asistir en procesos contables, elaborar estados financieros básicos, gestionar nómina y manejar software contable.',
        'modulos'=>[
            ['nombre'=>'Contabilidad general','horas'=>200,'docente'=>'Cont. Hernán Ospina'],
            ['nombre'=>'Ofimática avanzada','horas'=>120,'docente'=>'Ing. Patricia Mora'],
            ['nombre'=>'Nómina y prestaciones sociales','horas'=>160,'docente'=>'Cont. Hernán Ospina'],
            ['nombre'=>'Tributaria básica','horas'=>120,'docente'=>'Adm. Rosa Jiménez'],
            ['nombre'=>'Gestión documental','horas'=>80,'docente'=>'Adm. Rosa Jiménez'],
            ['nombre'=>'Práctica empresarial','horas'=>760,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>25,'inscritos'=>14,'inicio'=>'13 Ene 2026','fin'=>'08 Oct 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>25,'inscritos'=>15,'inicio'=>'13 Ene 2026','fin'=>'08 Oct 2026'],
            ['nombre'=>'Grupo C — Noche','jornada'=>'Noche','cupo'=>25,'inscritos'=>12,'inicio'=>'13 Ene 2026','fin'=>'08 Oct 2026'],
        ],
    ],
    [
        'id'=>6,'codigo'=>'ESA-EDU-001',
        'nombre'=>'Técnico Laboral en Asistente de Preescolar',
        'escuela'=>'Educación e Idiomas','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1600,'meses'=>10,'resolucion'=>'Res. 2750-2022 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde'],'cupo'=>22,'matricula'=>290000,'valor_programa'=>1490000,'valor_mensual'=>120000,
        'estudiantes'=>23,'estado'=>'activo',
        'descripcion'=>'Forma técnicos para apoyar procesos pedagógicos en educación inicial y desarrollar actividades lúdicas para el desarrollo integral de niños de 0 a 6 años.',
        'perfil_egreso'=>'Asistir al docente en preescolar, diseñar actividades lúdico-pedagógicas y apoyar el desarrollo integral de la primera infancia.',
        'modulos'=>[
            ['nombre'=>'Desarrollo infantil y psicología educativa','horas'=>200,'docente'=>'Psic. Ana Torres'],
            ['nombre'=>'Pedagogía para la primera infancia','horas'=>200,'docente'=>'Lic. Gloria Pinto'],
            ['nombre'=>'Lúdica y expresión artística','horas'=>160,'docente'=>'Lic. Gloria Pinto'],
            ['nombre'=>'Nutrición y salud infantil','horas'=>80,'docente'=>'Nutr. Carlos Díaz'],
            ['nombre'=>'Legislación educativa colombiana','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica pedagógica','horas'=>880,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>22,'inscritos'=>12,'inicio'=>'01 Mar 2026','fin'=>'26 Dic 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>22,'inscritos'=>11,'inicio'=>'01 Mar 2026','fin'=>'26 Dic 2026'],
        ],
    ],
    [
        'id'=>7,'codigo'=>'ESA-SAL-003',
        'nombre'=>'Técnico Laboral en Seguridad Ocupacional y Laboral',
        'escuela'=>'Salud','nivel'=>'Técnico Laboral','modalidad'=>'Mixta',
        'horas'=>1800,'meses'=>12,'resolucion'=>'Res. 5104-2023 · SED Bogotá',
        'jornadas'=>['Noche','Fines de semana'],'cupo'=>25,'matricula'=>370000,'valor_programa'=>2110000,'valor_mensual'=>145000,
        'estudiantes'=>31,'estado'=>'activo',
        'descripcion'=>'Forma técnicos para identificar, evaluar y controlar riesgos laborales, implementar programas de SST y gestionar la seguridad en ambientes de trabajo.',
        'perfil_egreso'=>'Implementar programas de SST, aplicar la normativa colombiana de seguridad laboral y gestionar riesgos en empresas.',
        'modulos'=>[
            ['nombre'=>'Fundamentos de SST','horas'=>160,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Identificación y control de riesgos','horas'=>200,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Normativa colombiana SST','horas'=>120,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Ergonomía y bienestar laboral','horas'=>120,'docente'=>'T.O. María Suárez'],
            ['nombre'=>'Primeros auxilios y emergencias','horas'=>80,'docente'=>'Enf. Lucía Vargas'],
            ['nombre'=>'Práctica empresarial SST','horas'=>1120,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Noche','jornada'=>'Noche','cupo'=>25,'inscritos'=>16,'inicio'=>'16 Feb 2026','fin'=>'30 Ene 2027'],
            ['nombre'=>'Grupo B — Fines de semana','jornada'=>'Fines de semana','cupo'=>25,'inscritos'=>15,'inicio'=>'21 Feb 2026','fin'=>'06 Feb 2027'],
        ],
    ],
    [
        'id'=>8,'codigo'=>'ESA-TUR-002',
        'nombre'=>'Técnico Laboral en Servicios Hoteleros y Turísticos',
        'escuela'=>'Cocina y Turismo','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1440,'meses'=>9,'resolucion'=>'En trámite · SED Bogotá',
        'jornadas'=>['Mañana'],'cupo'=>20,'matricula'=>310000,'valor_programa'=>1390000,'valor_mensual'=>120000,
        'estudiantes'=>15,'estado'=>'en_aprobacion',
        'descripcion'=>'Forma técnicos para laborar en hoteles, agencias de viaje y operadoras turísticas, con énfasis en servicio al cliente y operaciones hoteleras.',
        'perfil_egreso'=>'Desempeñarse en áreas de recepción, alojamiento y agencias de viaje, brindando servicio de excelencia al viajero.',
        'modulos'=>[
            ['nombre'=>'Fundamentos de turismo y hotelería','horas'=>160,'docente'=>'Adm. Diana Salcedo'],
            ['nombre'=>'Servicio al cliente turístico','horas'=>160,'docente'=>'Adm. Diana Salcedo'],
            ['nombre'=>'Operaciones hoteleras','horas'=>200,'docente'=>'Adm. Diana Salcedo'],
            ['nombre'=>'Inglés para turismo','horas'=>120,'docente'=>'Lic. Carlos Hoyos'],
            ['nombre'=>'Geografía turística de Colombia','horas'=>80,'docente'=>'Geogr. Patricia Vela'],
            ['nombre'=>'Práctica hotelera','horas'=>720,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>20,'inscritos'=>15,'inicio'=>'13 Abr 2026','fin'=>'08 Ene 2027'],
        ],
    ],
    [
        'id'=>9,'codigo'=>'ESA-DEP-001',
        'nombre'=>'Técnico Laboral en Salvamento Acuático',
        'escuela'=>'Deporte y Cultura','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1440,'meses'=>9,'resolucion'=>'Res. 1122-2021 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde'],'cupo'=>20,'matricula'=>260000,'valor_programa'=>1400000,'valor_mensual'=>126000,
        'estudiantes'=>28,'estado'=>'activo',
        'descripcion'=>'Forma técnicos en salvamento acuático, primeros auxilios y prevención de riesgos en piscinas, playas y entornos acuáticos.',
        'perfil_egreso'=>'Realizar rescates acuáticos, aplicar maniobras de reanimación y gestionar la seguridad en espacios acuáticos.',
        'modulos'=>[
            ['nombre'=>'Técnicas de natación avanzada','horas'=>200,'docente'=>'Lic. Juan Giraldo'],
            ['nombre'=>'Rescate y salvamento','horas'=>240,'docente'=>'Lic. Juan Giraldo'],
            ['nombre'=>'Primeros auxilios acuáticos','horas'=>160,'docente'=>'Enf. Lucía Vargas'],
            ['nombre'=>'Normativa y legislación acuática','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica en instalaciones acuáticas','horas'=>760,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>20,'inscritos'=>14,'inicio'=>'01 Mar 2026','fin'=>'27 Nov 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>20,'inscritos'=>14,'inicio'=>'01 Mar 2026','fin'=>'27 Nov 2026'],
        ],
    ],
    [
        'id'=>10,'codigo'=>'ESA-CIE-001',
        'nombre'=>'Técnico Laboral en Electricista',
        'escuela'=>'Ciencias','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1600,'meses'=>10,'resolucion'=>'Res. 4410-2022 · SED Bogotá',
        'jornadas'=>['Mañana','Noche'],'cupo'=>22,'matricula'=>310000,'valor_programa'=>1710000,'valor_mensual'=>140000,
        'estudiantes'=>35,'estado'=>'activo',
        'descripcion'=>'Forma técnicos para instalar, mantener y reparar sistemas eléctricos residenciales e industriales cumpliendo el Reglamento Técnico de Instalaciones Eléctricas (RETIE).',
        'perfil_egreso'=>'Ejecutar instalaciones eléctricas, leer planos eléctricos y aplicar normas de seguridad RETIE en ambientes residenciales e industriales.',
        'modulos'=>[
            ['nombre'=>'Fundamentos de electricidad','horas'=>200,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Instalaciones eléctricas residenciales','horas'=>240,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Instalaciones industriales','horas'=>200,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'RETIE y normativa eléctrica','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica en campo','horas'=>880,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>22,'inscritos'=>18,'inicio'=>'16 Feb 2026','fin'=>'11 Dic 2026'],
            ['nombre'=>'Grupo B — Noche','jornada'=>'Noche','cupo'=>22,'inscritos'=>17,'inicio'=>'16 Feb 2026','fin'=>'11 Dic 2026'],
        ],
    ],
    [
        'id'=>11,'codigo'=>'ESA-CIE-002',
        'nombre'=>'Técnico Laboral en Electromecánica',
        'escuela'=>'Ciencias','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1600,'meses'=>10,'resolucion'=>'Res. 3320-2021 · SED Bogotá',
        'jornadas'=>['Tarde','Noche'],'cupo'=>20,'matricula'=>320000,'valor_programa'=>1720000,'valor_mensual'=>140000,
        'estudiantes'=>22,'estado'=>'activo',
        'descripcion'=>'Capacita en el mantenimiento y reparación de equipos electromecánicos, motores eléctricos y sistemas automatizados en entornos industriales.',
        'perfil_egreso'=>'Mantener y reparar equipos electromecánicos, operar tableros de control y aplicar técnicas de mantenimiento preventivo y correctivo.',
        'modulos'=>[
            ['nombre'=>'Electricidad y magnetismo aplicada','horas'=>160,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Motores eléctricos y transformadores','horas'=>200,'docente'=>'Ing. Fabián Castro'],
            ['nombre'=>'Automatización industrial básica','horas'=>200,'docente'=>'Ing. Patricia Mora'],
            ['nombre'=>'Mantenimiento preventivo y correctivo','horas'=>160,'docente'=>'Ing. Patricia Mora'],
            ['nombre'=>'Práctica industrial','horas'=>880,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Tarde','jornada'=>'Tarde','cupo'=>20,'inscritos'=>11,'inicio'=>'13 Abr 2026','fin'=>'08 Feb 2027'],
            ['nombre'=>'Grupo B — Noche','jornada'=>'Noche','cupo'=>20,'inscritos'=>11,'inicio'=>'13 Abr 2026','fin'=>'08 Feb 2027'],
        ],
    ],
    [
        'id'=>12,'codigo'=>'ESA-CIE-003',
        'nombre'=>'Técnico Laboral en Criminalística e Investigación Judicial',
        'escuela'=>'Ciencias','nivel'=>'Técnico Laboral','modalidad'=>'Mixta',
        'horas'=>1800,'meses'=>12,'resolucion'=>'Res. 2890-2023 · SED Bogotá',
        'jornadas'=>['Noche','Fines de semana'],'cupo'=>25,'matricula'=>380000,'valor_programa'=>2140000,'valor_mensual'=>147000,
        'estudiantes'=>19,'estado'=>'activo',
        'descripcion'=>'Forma técnicos en recolección de evidencias, cadena de custodia y apoyo a procesos de investigación judicial conforme a la normativa colombiana.',
        'perfil_egreso'=>'Apoyar investigaciones judiciales, manejar evidencias criminales y elaborar informes periciales bajo supervisión.',
        'modulos'=>[
            ['nombre'=>'Fundamentos de criminalística','horas'=>200,'docente'=>'Crim. Elsa Rondón'],
            ['nombre'=>'Cadena de custodia','horas'=>160,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Medicina legal básica','horas'=>160,'docente'=>'Dr. Víctor Ángel'],
            ['nombre'=>'Fotografía forense','horas'=>120,'docente'=>'Crim. Elsa Rondón'],
            ['nombre'=>'Derecho penal colombiano','horas'=>80,'docente'=>'Abg. Pedro Cano'],
            ['nombre'=>'Práctica supervisada','horas'=>1080,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Noche','jornada'=>'Noche','cupo'=>25,'inscritos'=>10,'inicio'=>'01 Mar 2026','fin'=>'26 Feb 2027'],
            ['nombre'=>'Grupo B — Fines de semana','jornada'=>'Fines de semana','cupo'=>25,'inscritos'=>9,'inicio'=>'07 Mar 2026','fin'=>'07 Mar 2027'],
        ],
    ],
    [
        'id'=>13,'codigo'=>'ESA-BEL-001',
        'nombre'=>'Técnico Laboral en Barbería Profesional',
        'escuela'=>'Belleza','nivel'=>'Técnico Laboral','modalidad'=>'Presencial',
        'horas'=>1200,'meses'=>8,'resolucion'=>'Res. 0987-2022 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde','Noche'],'cupo'=>20,'matricula'=>240000,'valor_programa'=>1160000,'valor_mensual'=>115000,
        'estudiantes'=>33,'estado'=>'activo',
        'descripcion'=>'Forma técnicos en corte, afeitado, colorimetría y tratamientos capilares masculinos con énfasis en tendencias y atención al cliente.',
        'perfil_egreso'=>'Realizar cortes, afeitados y tratamientos capilares masculinos, gestionar una barbería y brindar servicio al cliente de calidad.',
        'modulos'=>[
            ['nombre'=>'Historia y tendencias de barbería','horas'=>80,'docente'=>'Barbero Iván Peña'],
            ['nombre'=>'Técnicas de corte masculino','horas'=>240,'docente'=>'Barbero Iván Peña'],
            ['nombre'=>'Afeitado clásico y cuidado de barba','horas'=>160,'docente'=>'Barbero Iván Peña'],
            ['nombre'=>'Colorimetría y tratamientos','horas'=>120,'docente'=>'Est. Diana Rueda'],
            ['nombre'=>'Práctica en salón','horas'=>600,'docente'=>'Coordinación académica'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>20,'inscritos'=>12,'inicio'=>'13 Ene 2026','fin'=>'05 Sep 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>20,'inscritos'=>12,'inicio'=>'13 Ene 2026','fin'=>'05 Sep 2026'],
            ['nombre'=>'Grupo C — Noche','jornada'=>'Noche','cupo'=>20,'inscritos'=>9,'inicio'=>'13 Ene 2026','fin'=>'05 Sep 2026'],
        ],
    ],
    [
        'id'=>14,'codigo'=>'ESA-EDU-002',
        'nombre'=>'Técnico Laboral en Inglés A1–B2',
        'escuela'=>'Educación e Idiomas','nivel'=>'Técnico Laboral','modalidad'=>'Mixta',
        'horas'=>960,'meses'=>6,'resolucion'=>'Res. 1560-2020 · SED Bogotá',
        'jornadas'=>['Mañana','Tarde','Noche','Fines de semana'],'cupo'=>25,'matricula'=>200000,'valor_programa'=>980000,'valor_mensual'=>130000,
        'estudiantes'=>47,'estado'=>'activo',
        'descripcion'=>'Desarrolla competencias comunicativas en inglés desde nivel básico hasta intermedio-alto, con enfoque en conversación, gramática y preparación para certificaciones internacionales.',
        'perfil_egreso'=>'Comunicarse en inglés en contextos laborales y cotidianos, alcanzar nivel B2 del Marco Común Europeo y apoyar procesos bilingües.',
        'modulos'=>[
            ['nombre'=>'Inglés A1 — Básico','horas'=>160,'docente'=>'Lic. Carlos Hoyos'],
            ['nombre'=>'Inglés A2 — Elemental','horas'=>160,'docente'=>'Lic. Carlos Hoyos'],
            ['nombre'=>'Inglés B1 — Intermedio','horas'=>200,'docente'=>'Lic. Carlos Hoyos'],
            ['nombre'=>'Inglés B2 — Intermedio alto','horas'=>200,'docente'=>'Lic. Ana Bernal'],
            ['nombre'=>'Conversación y práctica oral','horas'=>240,'docente'=>'Lic. Ana Bernal'],
        ],
        'grupos'=>[
            ['nombre'=>'Grupo A — Mañana','jornada'=>'Mañana','cupo'=>25,'inscritos'=>12,'inicio'=>'13 Ene 2026','fin'=>'10 Jul 2026'],
            ['nombre'=>'Grupo B — Tarde','jornada'=>'Tarde','cupo'=>25,'inscritos'=>12,'inicio'=>'13 Ene 2026','fin'=>'10 Jul 2026'],
            ['nombre'=>'Grupo C — Fines de semana','jornada'=>'Fines de semana','cupo'=>25,'inscritos'=>23,'inicio'=>'17 Ene 2026','fin'=>'18 Jul 2026'],
        ],
    ],
];

$totalEst   = array_sum(array_column($programas,'estudiantes'));
$totalGrupos= array_sum(array_map(fn($p)=>count($p['grupos']),$programas));
$activos    = count(array_filter($programas,fn($p)=>$p['estado']==='activo'));
$escuelas   = array_unique(array_column($programas,'escuela'));
@endphp

@section('content')
<div class="prg-wrap adm-anim">

    {{-- Encabezado --}}
    <div class="prg-header">
        <div class="prg-header-top">
            <h2 class="prg-title">Programas académicos</h2>
            <button class="prg-btn prg-btn-primary" id="btn-nuevo">
                <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Nuevo programa
            </button>
        </div>
        <p class="prg-sub">Aquí podrás registrar nuevos programas académicos, consultar y editar los ya existentes, actualizar su estado y organizarlos por nivel, escuela y modalidad. Usa los filtros para encontrar rápidamente el programa que necesitas gestionar.</p>
    </div>

    {{-- KPI chips --}}
    <div class="prg-kpis">
        <div class="prg-kpi-chip prg-kpi-blue">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ count($programas) }}</div>
                <div class="prg-kpi-lbl">Programas</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-orange">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $totalEst }}</div>
                <div class="prg-kpi-lbl">Estudiantes</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-green">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ $totalGrupos }}</div>
                <div class="prg-kpi-lbl">Grupos activos</div>
            </div>
        </div>
        <div class="prg-kpi-chip prg-kpi-navy">
            <div class="prg-kpi-icon">
                <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            </div>
            <div>
                <div class="prg-kpi-val">{{ count($escuelas) }}</div>
                <div class="prg-kpi-lbl">Escuelas</div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="prg-filters">
        <div class="prg-search-box">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="search" id="prg-search" placeholder="Buscar programa..." autocomplete="off">
        </div>
        <div class="app-combobox" id="prg-cb-filter-escuela" data-position="absolute"
             data-options='@json(array_values($escuelas))'>
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todas las escuelas</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="prg-filter-escuela" data-combobox-value>
        </div>
        <div class="app-combobox" id="prg-cb-filter-estado" data-position="absolute">
            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                <span data-combobox-label>Todos los estados</span>
                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
            </button>
            <div class="app-combobox-panel" data-combobox-panel hidden>
                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
            </div>
            <input type="hidden" id="prg-filter-estado" data-combobox-value>
        </div>
        <span class="prg-count-badge" id="prg-count">{{ count($programas) }} programas</span>
    </div>

    {{-- Tabla --}}
    <div class="prg-table-wrap">
        <table class="prg-table" id="prg-table">
            <thead>
                <tr>
                    <th>Programa</th>
                    <th>Escuela</th>
                    <th>Duración</th>
                    <th>Modalidad</th>
                    <th>Estudiantes</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($programas as $p)
                @php $esc = $escMap[$p['escuela']] ?? 'adm'; @endphp
                <tr class="prg-row"
                    data-nombre="{{ strtolower($p['nombre']) }}"
                    data-escuela="{{ $p['escuela'] }}"
                    data-estado="{{ $p['estado'] }}"
                    data-id="{{ $p['id'] }}">
                    <td>
                        <div class="prg-prog-cell">
                            <div class="prg-prog-avatar prg-esc-{{ $esc }}">
                                {{ strtoupper(substr($p['escuela'],0,2)) }}
                            </div>
                            <div>
                                <div class="prg-prog-name">{{ $p['nombre'] }}</div>
                                <div class="prg-prog-code">{{ $p['codigo'] }}</div>
                            </div>
                        </div>
                    </td>
                    <td><span class="prg-esc-tag prg-esc-{{ $esc }}">{{ $p['escuela'] }}</span></td>
                    <td class="prg-dur-cell">
                        <strong>{{ number_format($p['horas']) }}</strong> h
                        <span class="prg-dur-meses">· {{ $p['meses'] }} meses</span>
                    </td>
                    <td>{{ $p['modalidad'] }}</td>
                    <td>
                        <div class="prg-est-cell">
                            <strong>{{ $p['estudiantes'] }}</strong>
                            <div class="prg-est-bar">
                                <div class="prg-est-fill" style="width:{{ min(100, round($p['estudiantes']/$p['cupo']/count($p['grupos'])*100)) }}%"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        @if($p['estado']==='activo')
                            <span class="app-status-tag app-status-active">Activo</span>
                        @elseif($p['estado']==='en_aprobacion')
                            <span class="app-status-tag app-status-warn">En aprobación</span>
                        @else
                            <span class="app-status-tag app-status-muted">Inactivo</span>
                        @endif
                    </td>
                    <td class="prg-actions-cell">
                        <button class="prg-icon-btn" title="Ver detalle" data-id="{{ $p['id'] }}" data-action="ver">
                            <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="prg-icon-btn" title="Editar" data-id="{{ $p['id'] }}" data-action="editar">
                            <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="prg-empty" id="prg-empty" style="display:none">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <p>No hay programas que coincidan con la búsqueda</p>
        </div>
    </div>

    {{-- Paginación --}}
    <div class="prg-pagination" id="prg-pagination">
        <button class="prg-pg-btn prg-pg-prev" id="prg-pg-prev" disabled>
            <svg viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Anterior
        </button>
        <div class="prg-pg-pages" id="prg-pg-pages"></div>
        <button class="prg-pg-btn prg-pg-next" id="prg-pg-next">
            Siguiente
            <svg viewBox="0 0 24 24"><polyline points="9 6 15 12 9 18"/></svg>
        </button>
    </div>

</div>{{-- /prg-wrap --}}

{{-- ═══════════════════════════════════════════════════════
     DRAWER — Detalle de programa
     ═══════════════════════════════════════════════════════ --}}
<div class="prg-drawer" id="prg-drawer" aria-hidden="true">
    <div class="prg-drawer-overlay" id="prg-drawer-overlay"></div>
    <div class="prg-drawer-panel" role="dialog" aria-modal="true">

        {{-- Cabecera --}}
        <div class="prg-drawer-head">
            <div class="prg-drawer-head-info">
                <span class="prg-drawer-code" id="dw-codigo"></span>
                <h3 class="prg-drawer-title" id="dw-nombre"></h3>
                <div id="dw-badges" class="prg-drawer-badges"></div>
            </div>
            <button class="prg-close-btn" id="prg-drawer-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Tabs --}}
        <div class="prg-dtabs" role="tablist">
            <button class="prg-dtab is-active" data-tab="general" role="tab">General</button>
            <button class="prg-dtab" data-tab="plan" role="tab">Plan de estudios</button>
            <button class="prg-dtab" data-tab="grupos" role="tab">Grupos</button>
            <button class="prg-dtab" data-tab="estudiantes" role="tab">Estudiantes</button>
            <button class="prg-dtab" data-tab="documentos" role="tab">Documentos</button>
        </div>

        {{-- Cuerpo --}}
        <div class="prg-drawer-body">

            {{-- Tab General --}}
            <div class="prg-tab-panel is-active" data-panel="general">
                <div class="prg-info-grid">
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Escuela</span>
                        <span class="prg-info-val" id="dw-escuela"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Nivel</span>
                        <span class="prg-info-val" id="dw-nivel"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Modalidad</span>
                        <span class="prg-info-val" id="dw-modalidad"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Duración</span>
                        <span class="prg-info-val" id="dw-duracion"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Jornadas</span>
                        <span class="prg-info-val" id="dw-jornadas"></span>
                    </div>
                    <div class="prg-info-item">
                        <span class="prg-info-lbl">Cupo por grupo</span>
                        <span class="prg-info-val" id="dw-cupo"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Resolución de aprobación</span>
                        <span class="prg-info-val" id="dw-resolucion"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Descripción</span>
                        <span class="prg-info-val prg-info-text" id="dw-descripcion"></span>
                    </div>
                    <div class="prg-info-item prg-info-full">
                        <span class="prg-info-lbl">Perfil del egresado</span>
                        <span class="prg-info-val prg-info-text" id="dw-perfil"></span>
                    </div>
                </div>
                <div class="prg-costos-row prg-costos-2col">
                    <div class="prg-costo-card">
                        <div class="prg-costo-ico"><svg viewBox="0 0 24 24"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
                        <div class="prg-costo-lbl">Valor del programa</div>
                        <div class="prg-costo-val" id="dw-valor-programa"></div>
                    </div>
                    <div class="prg-costo-card">
                        <div class="prg-costo-ico"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div>
                        <div class="prg-costo-lbl">Matrícula (pago inicial)</div>
                        <div class="prg-costo-val" id="dw-matricula"></div>
                    </div>
                </div>
                <div class="prg-planes-section">
                    <div class="prg-planes-title">Valor mensual del sistema</div>
                    <div class="prg-plan-row">
                        <span class="prg-plan-nombre">Cuota mensual</span>
                        <span class="prg-plan-detalle" id="dw-valor-mensual"></span>
                    </div>
                    <div class="prg-planes-nota">El estudiante elige si paga mensual o semestral al matricularse.</div>
                </div>
            </div>

            {{-- Tab Plan de estudios --}}
            <div class="prg-tab-panel" data-panel="plan">
                <div class="prg-plan-header">
                    <span>Plan de estudios</span>
                    <span class="prg-plan-total" id="dw-horas-total"></span>
                </div>
                <table class="prg-modulos-table">
                    <thead>
                        <tr><th>#</th><th>Módulo / Asignatura</th><th>Horas</th><th>Docente asignado</th></tr>
                    </thead>
                    <tbody id="dw-modulos-body"></tbody>
                </table>
            </div>

            {{-- Tab Grupos --}}
            <div class="prg-tab-panel" data-panel="grupos">
                <div id="dw-grupos-grid" class="prg-grupos-grid"></div>
            </div>

            {{-- Tab Estudiantes --}}
            <div class="prg-tab-panel" data-panel="estudiantes">
                <div class="prg-placeholder">
                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <p id="dw-est-txt"></p>
                    <span>Vista detallada disponible en el módulo Estudiantes</span>
                </div>
            </div>

            {{-- Tab Documentos --}}
            <div class="prg-tab-panel" data-panel="documentos">
                <div class="prg-docs-list">
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Resolución de aprobación</div>
                            <div class="prg-doc-meta" id="dw-doc-res"></div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Pensum académico</div>
                            <div class="prg-doc-meta">Plan de estudios completo</div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <div class="prg-doc-item">
                        <div class="prg-doc-ico">
                            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="prg-doc-info">
                            <div class="prg-doc-name">Formato de matrícula</div>
                            <div class="prg-doc-meta">Formulario para inscripción de estudiantes</div>
                        </div>
                        <span class="prg-doc-badge">PDF</span>
                    </div>
                    <button class="prg-doc-add">
                        <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Subir documento
                    </button>
                </div>
            </div>

        </div>{{-- /prg-drawer-body --}}

        {{-- Footer del drawer --}}
        <div class="prg-drawer-foot">
            <button class="prg-btn prg-btn-secondary" id="prg-drawer-edit-btn">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar programa
            </button>
            <button class="prg-btn prg-btn-danger" id="prg-drawer-toggle-btn">
                Desactivar programa
            </button>
        </div>

    </div>{{-- /prg-drawer-panel --}}
</div>

{{-- ═══════════════════════════════════════════════════════
     MODAL — Crear / Editar programa (wizard 3 pasos)
     ═══════════════════════════════════════════════════════ --}}
<div class="prg-modal" id="prg-modal" aria-hidden="true">
    <div class="prg-modal-overlay" id="prg-modal-overlay"></div>
    <div class="prg-modal-box" role="dialog" aria-modal="true">

        <div class="prg-modal-head">
            <h3 class="prg-modal-title" id="prg-modal-title">Nuevo programa</h3>
            <button class="prg-close-btn" id="prg-modal-close" aria-label="Cerrar">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Barra de pasos --}}
        <div class="prg-wizard-bar">
            <div class="prg-wstep is-active" data-step="1">
                <div class="prg-wdot">1</div>
                <span>Info general</span>
            </div>
            <div class="prg-wline"></div>
            <div class="prg-wstep" data-step="2">
                <div class="prg-wdot">2</div>
                <span>Oferta</span>
            </div>
            <div class="prg-wline"></div>
            <div class="prg-wstep" data-step="3">
                <div class="prg-wdot">3</div>
                <span>Costos</span>
            </div>
        </div>

        <div class="prg-modal-body">

            {{-- Paso 1: Info general --}}
            <div class="prg-mstep is-active" data-step="1">
                <div class="prg-form-grid">
                    <div class="prg-field prg-field-full">
                        <label>Nombre del programa <span class="prg-req">*</span></label>
                        <input type="text" placeholder="Ej. Técnico Laboral en Servicios Geriátricos">
                    </div>
                    <div class="prg-field">
                        <label>Código interno</label>
                        <input type="text" placeholder="Ej. ESA-SAL-001">
                    </div>
                    <div class="prg-field">
                        <label>Nº Resolución de aprobación</label>
                        <input type="text" placeholder="Ej. Res. 4521-2022 · SED Bogotá">
                    </div>
                    <div class="prg-field">
                        <label>Nivel <span class="prg-req">*</span></label>
                        <div class="app-combobox" id="prg-cb-nivel">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar...</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="prg-inp-nivel" data-combobox-value>
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Escuela / Área <span class="prg-req">*</span></label>
                        <div class="app-combobox" id="prg-cb-escuela">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar...</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <div class="app-combobox-search-wrap">
                                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                                    <input type="text" class="app-combobox-search" data-combobox-search placeholder="Buscar...">
                                </div>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="prg-inp-escuela" data-combobox-value>
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Modalidad <span class="prg-req">*</span></label>
                        <div class="app-combobox" id="prg-cb-modalidad">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar...</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="prg-inp-modalidad" data-combobox-value>
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Duración en horas <span class="prg-req">*</span></label>
                        <input type="number" placeholder="Ej. 1800" min="160">
                    </div>
                    <div class="prg-field prg-field-full">
                        <label>Descripción del programa</label>
                        <textarea rows="3" placeholder="Describe brevemente el programa, su enfoque y objetivos..."></textarea>
                    </div>
                    <div class="prg-field prg-field-full">
                        <label>Perfil del egresado</label>
                        <textarea rows="2" placeholder="Competencias y habilidades al finalizar el programa..."></textarea>
                    </div>
                </div>
            </div>

            {{-- Paso 2: Oferta y grupos --}}
            <div class="prg-mstep" data-step="2">
                <div class="prg-form-grid">
                    <div class="prg-field prg-field-full">
                        <label>Jornadas disponibles <span class="prg-req">*</span></label>
                        <div class="prg-checks">
                            <label class="prg-chk"><input type="checkbox" value="Mañana"><span>Mañana</span></label>
                            <label class="prg-chk"><input type="checkbox" value="Tarde"><span>Tarde</span></label>
                            <label class="prg-chk"><input type="checkbox" value="Noche"><span>Noche</span></label>
                            <label class="prg-chk"><input type="checkbox" value="Fines de semana"><span>Fines de semana</span></label>
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Cupo máximo por grupo <span class="prg-req">*</span></label>
                        <input type="number" placeholder="Ej. 25" min="1" max="60">
                    </div>
                    <div class="prg-field">
                        <label>Fecha de inicio</label>
                        <input type="date">
                    </div>
                    <div class="prg-field">
                        <label>Duración en meses</label>
                        <input type="number" placeholder="Ej. 12" min="1">
                    </div>
                    <div class="prg-field prg-field-full">
                        <label>Estado del programa <span class="prg-req">*</span></label>
                        <div class="app-combobox" id="prg-cb-estado">
                            <button type="button" class="app-combobox-trigger" data-combobox-trigger>
                                <span data-combobox-label>Seleccionar...</span>
                                <svg class="app-combobox-chevron" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
                            </button>
                            <div class="app-combobox-panel" data-combobox-panel hidden>
                                <ul class="app-combobox-list" data-combobox-list role="listbox"></ul>
                            </div>
                            <input type="hidden" id="prg-inp-estado" data-combobox-value>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Paso 3: Costos --}}
            <div class="prg-mstep" data-step="3">
                <div class="prg-form-grid">
                    <div class="prg-field prg-field-full">
                        <label>Valor total del programa <span class="prg-req">*</span></label>
                        <div class="prg-input-pfx">
                            <span>$</span>
                            <input type="number" id="prg-inp-valor-programa" placeholder="2000000" min="0">
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Matrícula (pago inicial) <span class="prg-req">*</span></label>
                        <div class="prg-input-pfx">
                            <span>$</span>
                            <input type="number" id="prg-inp-matricula" placeholder="350000" min="0">
                        </div>
                    </div>
                    <div class="prg-field">
                        <label>Valor mensual del sistema <span class="prg-req">*</span></label>
                        <div class="prg-input-pfx">
                            <span>$</span>
                            <input type="number" id="prg-inp-valor-mensual" placeholder="140000" min="0">
                        </div>
                    </div>
                </div>
                <div class="prg-costo-aviso">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    El estudiante elige si paga mensual o semestral al momento de matricularse. El sistema calcula el total según su elección.
                </div>
            </div>

        </div>{{-- /prg-modal-body --}}

        <div class="prg-modal-foot">
            <button class="prg-btn prg-btn-ghost" id="prg-btn-back">Atrás</button>
            <div class="prg-modal-foot-right">
                <span class="prg-step-lbl" id="prg-step-lbl">Paso 1 de 3</span>
                <button class="prg-btn prg-btn-primary" id="prg-btn-next">Siguiente</button>
            </div>
        </div>

    </div>{{-- /prg-modal-box --}}
</div>
@endsection

@push('scripts')
<script>var PRG_DATA = @json($programas);</script>
<script src="{{ asset('assets/js/admin/programas.js') }}" defer></script>
@endpush
