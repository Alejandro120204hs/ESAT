{{-- Datos del panel Docente (PanelDocenteService::datos, los pasa cada controlador) y su guardado:
     public/assets/js/docente/datos.js arma window.DOC_DATOS / DOC_API. Va antes del JS de la pantalla. También los datos
     legales de ESAT y la sede para la lista de clase y la planilla de notas que imprime (documento.js). --}}
@php
    $docServidor = $datos + [
        'rutas' => [
            'asistencia' => url('docente/asistencia/__GRUPO__'),
            'notas'      => url('docente/notas/__GRUPO__/__MODULO__'),
            'sinClase'   => url('docente/asistencia/__GRUPO__/sin-clase'),
            'conClase'   => url('docente/asistencia/__GRUPO__/con-clase'),
        ],
        'institucion' => \App\Models\Institucional\Configuracion::institucion(),
        'sede'        => auth()->user()->sede?->only(['nombre', 'direccion', 'ciudad']),
        'docente'     => auth()->user()->name,
        'logo'        => asset('assets/img/logo.png'),
    ];
@endphp
<script>
    window.DOC_SERVIDOR = @json($docServidor);
</script>
<script src="{{ asset('assets/js/docente/datos.js') }}?v={{ filemtime(public_path('assets/js/docente/datos.js')) }}"></script>
