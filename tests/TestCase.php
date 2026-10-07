<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas corren en SQLite en memoria, que no tiene la función FIELD() de MySQL
        // (Curso::sesiones() la usa para ordenar por día de la semana).
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::connection()->getPdo()->sqliteCreateFunction('FIELD', function ($valor, ...$lista) {
                $i = array_search($valor, $lista, true);

                return $i === false ? 0 : $i + 1;
            });
        }
    }
}
