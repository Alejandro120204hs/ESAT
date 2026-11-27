<?php

// Datos del sitio público que comparten el header, el footer y las páginas.
// Escuelas, programas, sedes y contacto son reales: no inventar ni aproximar.
// Cada programa es [nombre visible, slug de la ruta programas.{slug}].
// 'foto' es el nombre (sin extensión) de la foto en public/assets/img (hay .webp y -960.webp).

return [

    'escuelas' => [
        [
            'slug'      => 'salud-animal-y-agropecuaria',
            'foto'      => 'veterinaria',
            'nombre'    => 'Escuela de Salud Animal y Agropecuaria',
            'corto'     => 'Salud Animal y Agropecuaria',
            'icono'     => 'ico-vet',
            'programas' => [
                ['Asistente de Medicina Veterinaria', 'asistente-de-medicina-veterinaria'],
                ['Asistente de Veterinaria y Zootecnia', 'asistente-de-veterinaria-y-zootecnia'],
                ['Producción Agropecuaria y Zootecnia', 'produccion-agropecuaria-y-zootecnia'],
                ['Asistente de Laboratorio Experto en Animales', 'asistente-de-laboratorio-experto-en-animales'],
            ],
        ],
        [
            'slug'      => 'ingenieria-y-tecnologia',
            'foto'      => 'ia',
            'nombre'    => 'Escuela de Ingeniería y Tecnología',
            'corto'     => 'Ingeniería y Tecnología',
            'icono'     => 'ico-tool',
            'programas' => [
                ['Electromecánica', 'electromecanica'],
                ['Electricista', 'electricista'],
                ['Mecánica de Motos', 'mecanica-de-motos'],
                ['Mecánico Electrónico', 'mecanico-electronico'],
                ['Técnico en Robótica', 'tecnico-en-robotica'],
                ['Obras Civiles y Arquitectura', 'obras-civiles-y-arquitectura'],
                ['Operación y Mantenimiento de Maquinaria Pesada', 'operacion-y-mantenimiento-de-maquinaria-pesada'],
                ['Energías Alternativas y Renovables (Paneles Solares)', 'energias-renovables-y-alternativas'],
                ['Órtesis y Prótesis Médicas', 'ortesis-y-protesis-medicas'],
            ],
        ],
        [
            'slug'      => 'salud-y-bienestar',
            'foto'      => 'camillero',
            'nombre'    => 'Escuela de Salud y Bienestar',
            'corto'     => 'Salud y Bienestar',
            'icono'     => 'ico-salud',
            'programas' => [
                ['Asistente de Preescolar', 'asistente-de-preescolar'],
                ['Auxiliar de Psiquiatría', 'auxiliar-de-psiquiatria'],
                ['Camillero Hospitalario', 'camillero-hospitalario'],
                ['Servicio Social y Comunitario', 'servicio-social-y-comunitario'],
                ['Podología y Estética de Pies', 'podologia-y-estetica-de-pies'],
                ['Salvamento Acuático', 'salvamento-acuatico'],
                ['Barbería', 'barberia'],
                ['Salud Natural y Terapias Complementarias', 'salud-natural-y-terapias-complementarias'],
                ['Instructor de Natación', 'instructor-de-natacion'],
            ],
        ],
        [
            'slug'      => 'artes-y-cultura',
            'foto'      => 'artistica',
            'nombre'    => 'Escuela de Artes y Cultura',
            'corto'     => 'Artes y Cultura',
            'icono'     => 'ico-palette',
            'programas' => [
                ['Promoción y Gestión Artística', 'promocion-y-gestion-artistica'],
                ['Titiritero', 'titiritero'],
                ['Compositor Coreográfico e Intérprete de Danzas', 'compositor-coreografico-e-interprete-de-danzas'],
            ],
        ],
        [
            'slug'      => 'gastronomia-y-turismo',
            'foto'      => 'cocina',
            'nombre'    => 'Escuela de Gastronomía y Turismo',
            'corto'     => 'Gastronomía y Turismo',
            'icono'     => 'ico-cocina',
            'programas' => [
                ['Chef de Alta Cocina', 'chef-de-alta-cocina'],
                ['Sommelier', 'sommelier'],
                ['Gastronomía y Artes Culinarias', 'gastronomia-y-artes-culinarias'],
                ['Servicios de Recreación y Deporte', 'servicios-de-recreacion-y-deportes'],
                ['Servicios Hoteleros y Turísticos', 'servicios-hoteleros-y-turisticos'],
                ['Líneas Aéreas, Marítimas y Terrestres', 'lineas-aereas-maritimas-y-terrestres'],
            ],
        ],
        [
            'slug'      => 'administrativa-y-financiera',
            'foto'      => 'contable',
            'nombre'    => 'Escuela Administrativa y Financiera',
            'corto'     => 'Administrativa y Financiera',
            'icono'     => 'ico-admin',
            'programas' => [
                ['Auxiliar Contable y Administrativo', 'auxiliar-contable-y-administrativo'],
                ['Auditoría y Facturación de Cuentas Médicas', 'auditoria-y-facturacion-de-cuentas-medicas'],
                ['Marketing y Redes Sociales', 'marketing-y-redes-sociales'],
                ['Asesor de Negocios Institucional PYME', 'asesor-de-negocios-institucional-pyme'],
            ],
        ],
        [
            'slug'      => 'seguridad-y-justicia',
            'foto'      => 'transito',
            'nombre'    => 'Escuela de Seguridad y Justicia',
            'corto'     => 'Seguridad y Justicia',
            'icono'     => 'ico-shield',
            'programas' => [
                ['Agente de Tránsito', 'agente-de-transito'],
                ['Criminalística, Investigación Judicial y Ciencias Forenses', 'criminalistica-investigacion-judicial-y-ciencias-forenses'],
                ['Inspector de Control de Calidad de Alimentos y Bebidas', 'inspector-de-control-de-calidad-de-alimentos-y-bebidas'],
                ['Seguridad y Salud en el Trabajo', 'seguridad-y-salud-en-el-trabajo'],
            ],
        ],
        [
            'slug'      => 'idiomas',
            'foto'      => 'aprender-ingles',
            'nombre'    => 'Escuela de Idiomas',
            'corto'     => 'Idiomas',
            'icono'     => 'ico-idiomas',
            'programas' => [
                ['Inglés A1–A2–B1–B2', 'ingles-a1-a2-b1-b2'],
                ['Francés A1–A2–B1–B2', 'frances-a1-a2-b1-b2'],
            ],
        ],
    ],

    // Direcciones de antes del catálogo del 16-11-2026 (afiche "Carreras técnicas" de ESAT): redirigen
    // (301) al programa o la escuela de ahora, para no romper los enlaces ya compartidos.
    'redirecciones' => [
        'asistente-de-laboratorio-clinico-veterinario' => 'asistente-de-laboratorio-experto-en-animales',
        'mecanica-y-electronica-de-motos'              => 'mecanica-de-motos',
        'gestion-y-promocion-artistica'                => 'promocion-y-gestion-artistica',
        'cocina-nacional-e-internacional'              => 'chef-de-alta-cocina',
        'inspector-de-calidad-de-alimentos-y-bebidas'  => 'inspector-de-control-de-calidad-de-alimentos-y-bebidas',
        'seguridad-ocupacional-y-laboral'              => 'seguridad-y-salud-en-el-trabajo',
        'servicios-geriatricos'                        => 'escuelas/salud-y-bienestar',
        'escuelas/salud'                               => 'escuelas/salud-y-bienestar',
        'escuelas/cocina-y-turismo'                    => 'escuelas/gastronomia-y-turismo',
        'escuelas/administrativa'                      => 'escuelas/administrativa-y-financiera',
        'escuelas/deporte-y-cultura'                   => 'escuelas/artes-y-cultura',
        'escuelas/ciencias'                            => 'escuelas/ingenieria-y-tecnologia',
        'escuelas/educacion-e-idiomas'                 => 'escuelas/idiomas',
        'escuelas/belleza'                             => 'escuelas/salud-y-bienestar',
        // Educación Continuada salió del sitio (17-11-2026): sus 7 líneas llevan al inicio
        'salud-ocupacional'                            => '',
        'areas-sociales'                               => '',
        'pedagogia'                                    => '',
        'administracion-y-gerencia'                    => '',
        'edificios-y-afines'                           => '',
        'hoteleria-y-turismo'                          => '',
        'salud'                                        => '',
    ],

    // Las sedes del afiche "Carreras técnicas" (19-11-2026), con su teléfono de WhatsApp: [número internacional, como se muestra].
    // Marquetalia todavía sin dirección: sale el municipio y el mapa marca el centro del pueblo.
    'sedes' => [
        ['nombre' => 'Sasaima', 'direccion' => 'Urbanización San José, Cra 2 #8-84, Sasaima, Cundinamarca', 'lat' => 5.0128, 'lng' => -74.4361, 'telefono' => ['573123773144', '312 377 3144']],
        ['nombre' => 'Guaduas', 'direccion' => 'Cra 2 No. 1-83, Calle de la Pola, Cundinamarca', 'lat' => 5.0700, 'lng' => -74.5928, 'telefono' => ['573219572058', '321 957 2058']],
        ['nombre' => 'La Dorada', 'direccion' => 'Calle 9 No. 7-55, Barrio Magdalena, La Dorada, Caldas', 'lat' => 5.4544, 'lng' => -74.6379, 'telefono' => ['573216291992', '321 629 1992']],
        ['nombre' => 'Marquetalia', 'direccion' => 'Marquetalia, Caldas', 'lat' => 5.2975, 'lng' => -75.0533, 'telefono' => ['573234883430', '323 488 3430']],
    ],

    // Números de WhatsApp: [número internacional, como se muestra].
    'whatsapp' => [
        ['573152957588', '315 295 75 88'],
        ['573123773144', '312 377 31 44'],
    ],

    // Redes sociales: vacías hasta tener las URL reales (no inventarlas).
    // Formato: ['red' => 'facebook|instagram|x|youtube', 'url' => 'https://…'].
    'redes' => [],

];
