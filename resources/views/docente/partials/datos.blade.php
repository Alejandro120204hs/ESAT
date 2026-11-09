{{-- Datos del panel Docente (PanelDocenteService::datos, los pasa cada controlador) y su guardado:
     public/assets/js/docente/datos.js arma window.DOC_DATOS / DOC_API. Va antes del JS de la pantalla. --}}
<script>
    window.DOC_SERVIDOR = @json($datos + ['rutas' => [
        'asistencia' => url('docente/asistencia/__GRUPO__'),
        'notas'      => url('docente/notas/__GRUPO__/__MODULO__'),
    ]]);
</script>
<script src="{{ asset('assets/js/docente/datos.js') }}?v={{ filemtime(public_path('assets/js/docente/datos.js')) }}"></script>
