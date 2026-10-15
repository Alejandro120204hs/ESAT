<?php

use Illuminate\Foundation\Inspiring;
use App\Services\Finanzas\PagoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Marca como vencidas las cuotas pendientes que ya pasaron su fecha (las pantallas igual lo calculan por fecha)
Artisan::command('pagos:vencer', function (PagoService $pagos) {
    $this->info('Cuotas marcadas como vencidas: '.$pagos->vencerAtrasados());
})->purpose('Marca como vencidos los cobros pendientes cuya fecha de vencimiento ya pasó');

Schedule::command('pagos:vencer')->dailyAt('00:10');
