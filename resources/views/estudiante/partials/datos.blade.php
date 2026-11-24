{{-- Lo que el panel Estudiante recibe del servidor (window.EST_SERVIDOR): los datos de PanelEstudianteService::datos
     (el controlador de cada pantalla los pasa como $datos), los datos legales de ESAT y la sede para los documentos
     imprimibles (Admin › Configuración) y las rutas donde guarda. datos.js arma EST_DATOS y EST_API. --}}
@php
    $estServidor = [
        'datos'       => $datos ?? [],
        'institucion' => \App\Models\Institucional\Configuracion::institucion(),
        'sede'        => auth()->user()->sede?->only(['nombre', 'direccion', 'ciudad']),
        'logo'        => asset('assets/img/logo.png'),
        'rutas'       => [
            'plan'      => route('estudiante.pagos.plan'),
            'responder' => route('estudiante.aula.responder', ['evaluacion' => '__ID__']),
            'entregar'  => route('estudiante.aula.entregar', ['actividad' => '__ID__']),
            'leido'     => route('estudiante.comunicados.leido', ['comunicado' => '__ID__']),
            'tema'      => route('estudiante.foro.tema'),
            'responderTema' => route('estudiante.foro.responder', ['tema' => '__ID__']),
            'reportar'  => route('estudiante.foro.reportar', ['mensaje' => '__ID__']),
        ],
    ];
@endphp
<script>
    window.EST_SERVIDOR = @json($estServidor);
</script>
<script src="{{ asset('assets/js/estudiante/datos.js') }}?v={{ filemtime(public_path('assets/js/estudiante/datos.js')) }}"></script>
