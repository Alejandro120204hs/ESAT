<?php

// Datos del sitio público que comparten el header, el footer y las páginas.
// Escuelas, programas, sedes y contacto son reales: no inventar ni aproximar.
// Cada programa es [nombre visible, slug de la ruta programas.{slug}].
// 'foto' es el nombre (sin extensión) de la foto en public/assets/img (hay .webp y -960.webp).

return [

    'escuelas' => [
        [
            'slug'      => 'salud',
            'foto'      => 'servicio-geriatrico',
            'nombre'    => 'Escuela de Salud',
            'corto'     => 'Salud',
            'icono'     => 'ico-salud',
            'programas' => [
                ['Servicios Geriátricos', 'servicios-geriatricos'],
                ['Seguridad Ocupacional y Laboral', 'seguridad-ocupacional-y-laboral'],
                ['Auxiliar de Psiquiatría', 'auxiliar-de-psiquiatria'],
                ['Camillero Hospitalario', 'camillero-hospitalario'],
            ],
        ],
        [
            'slug'      => 'cocina-y-turismo',
            'foto'      => 'cocina',
            'nombre'    => 'Escuela de Cocina y Turismo',
            'corto'     => 'Cocina y Turismo',
            'icono'     => 'ico-cocina',
            'programas' => [
                ['Servicios Hoteleros y Turísticos', 'servicios-hoteleros-y-turisticos'],
                ['Cocina Nacional e Internacional', 'cocina-nacional-e-internacional'],
                ['Sommelier', 'sommelier'],
                ['Inspector de Calidad de Alimentos y Bebidas', 'inspector-de-calidad-de-alimentos-y-bebidas'],
            ],
        ],
        [
            'slug'      => 'administrativa',
            'foto'      => 'contable',
            'nombre'    => 'Escuela Administrativa',
            'corto'     => 'Administrativa',
            'icono'     => 'ico-admin',
            'programas' => [
                ['Auditoría y Facturación de Cuentas Médicas', 'auditoria-y-facturacion-de-cuentas-medicas'],
                ['Agente de Tránsito', 'agente-de-transito'],
                ['Auxiliar Contable y Administrativo', 'auxiliar-contable-y-administrativo'],
            ],
        ],
        [
            'slug'      => 'deporte-y-cultura',
            'foto'      => 'salvamento',
            'nombre'    => 'Escuela Deporte y Cultura',
            'corto'     => 'Deporte y Cultura',
            'icono'     => 'ico-deporte',
            'programas' => [
                ['Salvamento Acuático', 'salvamento-acuatico'],
                ['Gestión y Promoción Artística', 'gestion-y-promocion-artistica'],
                ['Servicios de Recreación y Deportes', 'servicios-de-recreacion-y-deportes'],
            ],
        ],
        [
            'slug'      => 'ciencias',
            'foto'      => 'Obras-civiles',
            'nombre'    => 'Escuela Ciencias',
            'corto'     => 'Ciencias',
            'icono'     => 'ico-ciencias',
            'programas' => [
                ['Producción Agropecuaria y Zootecnia', 'produccion-agropecuaria-y-zootecnia'],
                ['Asistente de Veterinaria y Zootecnia', 'asistente-de-veterinaria-y-zootecnia'],
                ['Obras Civiles y Arquitectura', 'obras-civiles-y-arquitectura'],
                ['Criminalística, Investigación Judicial y Ciencias Forenses', 'criminalistica-investigacion-judicial-y-ciencias-forenses'],
                ['Energías Renovables', 'energias-renovables-y-alternativas'],
                ['Asistente Lab. Clínico Veterinario', 'asistente-de-laboratorio-clinico-veterinario'],
                ['Electricista', 'electricista'],
                ['Electromecánica', 'electromecanica'],
                ['Mecánica y Electrónica de Motos', 'mecanica-y-electronica-de-motos'],
            ],
        ],
        [
            'slug'      => 'educacion-e-idiomas',
            'foto'      => 'aprender-ingles',
            'nombre'    => 'Escuela de Educación e Idiomas',
            'corto'     => 'Educación e Idiomas',
            'icono'     => 'ico-idiomas',
            'programas' => [
                ['Inglés A1–A2–B1–B2', 'ingles-a1-a2-b1-b2'],
                ['Francés A1–A2–B1–B2', 'frances-a1-a2-b1-b2'],
                ['Asistente de Preescolar', 'asistente-de-preescolar'],
            ],
        ],
        [
            'slug'      => 'belleza',
            'foto'      => 'barberia',
            'nombre'    => 'Escuela de Belleza',
            'corto'     => 'Belleza',
            'icono'     => 'ico-belleza',
            'programas' => [
                ['Barbería', 'barberia'],
            ],
        ],
    ],

    // Educación Continuada: [nombre, slug de la ruta continuada.{slug}].
    'continuada' => [
        ['Salud Ocupacional', 'salud-ocupacional'],
        ['Áreas Sociales', 'areas-sociales'],
        ['Pedagogía', 'pedagogia'],
        ['Administración y Gerencia', 'administracion-y-gerencia'],
        ['Edificios y Afines', 'edificios-y-afines'],
        ['Hotelería y Turismo', 'hoteleria-y-turismo'],
        ['Salud', 'salud'],
    ],

    'sedes' => [
        ['nombre' => 'Bogotá', 'direccion' => 'Cra 52 #71-18', 'lat' => 4.6682, 'lng' => -74.0817],
        ['nombre' => 'Sasaima', 'direccion' => 'Urbanización San José, Cra 2 #8-84, Sasaima, Cundinamarca', 'lat' => 5.0128, 'lng' => -74.4361],
        ['nombre' => 'Guaduas', 'direccion' => 'Cra 2 No. 1-83, Calle de la Pola, Cundinamarca', 'lat' => 5.0700, 'lng' => -74.5928],
        ['nombre' => 'La Dorada', 'direccion' => 'Calle 9 No. 7-55, Barrio Magdalena, La Dorada, Caldas', 'lat' => 5.4544, 'lng' => -74.6379],
    ],

    // Sedes de franquicia: la lista que envió ESAT el 10-11-2026, en ese orden. Todavía sin dirección ni
    // contacto propio: el sitio solo nombra el municipio (sin mapa) y ofrece preguntar por WhatsApp.
    // Cuando llegue la dirección de una, pasa a 'sedes' con sus coordenadas reales.
    'franquicias' => ['La Dorada', 'La Vega', 'El Vino', 'Supatá', 'San Francisco', 'El Rosal', 'Anolaima', 'Cachipay', 'Albán'],

    // Números de WhatsApp: [número internacional, como se muestra].
    'whatsapp' => [
        ['573152957588', '315 295 75 88'],
        ['573123773144', '312 377 31 44'],
    ],

    // Redes sociales: vacías hasta tener las URL reales (no inventarlas).
    // Formato: ['red' => 'facebook|instagram|x|youtube', 'url' => 'https://…'].
    'redes' => [],

];
