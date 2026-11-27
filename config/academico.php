<?php

return [
    /*
     * Festivos de Colombia (App\Support\Festivos): en un festivo no hay clase, salvo que el docente tome asistencia ese
     * día. Se apagan con ACADEMICO_FESTIVOS=false (las pruebas que no son de festivos lo hacen, para que sus fechas fijas
     * no cambien).
     */
    'festivos' => env('ACADEMICO_FESTIVOS', true),
];
