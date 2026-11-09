<?php

namespace Tests\Feature\Sitio;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sitio público: cada programa, línea de Educación Continuada y escuela
 * de config/contenido.php y config/sitio.php tiene su página, con su
 * nombre de ruta de siempre y sin avisos de "foto pendiente".
 */
class PaginasTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_programa_tiene_su_pagina_con_su_contenido(): void
    {
        foreach (config('contenido.programas') as $slug => $p) {
            $this->get(route('programas.' . $slug))
                ->assertOk()
                ->assertSee($p['nombre'])
                ->assertSee($p['lema'])
                ->assertSee($p['competencias'][0][1])
                ->assertSee($p['practica'][1])
                ->assertDontSee('Foto pendiente');
        }
    }

    public function test_cada_programa_esta_en_una_escuela_y_su_ruta_es_la_misma(): void
    {
        $enEscuelas = collect(config('sitio.escuelas'))->flatMap(fn ($e) => array_column($e['programas'], 1))->sort()->values()->all();
        $conPagina = collect(config('contenido.programas'))->keys()->sort()->values()->all();

        $this->assertSame($conPagina, $enEscuelas);
        $this->assertSame(url('/barberia'), route('programas.barberia'));
    }

    public function test_cada_linea_de_educacion_continuada_tiene_su_pagina(): void
    {
        foreach (config('contenido.continuada') as $slug => $linea) {
            $respuesta = $this->get(route('continuada.' . $slug))->assertOk()->assertSee($linea['nombre']);
            foreach ($linea['cursos'] as $curso) {
                $respuesta->assertSee($curso);
            }
        }
    }

    public function test_cada_escuela_lista_sus_programas(): void
    {
        foreach (config('sitio.escuelas') as $escuela) {
            $respuesta = $this->get(route('escuelas.' . $escuela['slug']))->assertOk()->assertSee($escuela['nombre']);
            foreach ($escuela['programas'] as [, $slug]) {
                $respuesta->assertSee(route('programas.' . $slug), false);
            }
        }
    }

    public function test_el_cierre_del_footer_invita_al_programa_de_la_pagina(): void
    {
        $this->get(route('programas.barberia'))
            ->assertSee('¿Listo para formarte en Barbería?')
            ->assertSee(rawurlencode('Hola ESAT, quiero información sobre: Barbería'), false);

        $this->get(route('home'))->assertSee('Tu formación técnica empieza con');
    }

    public function test_una_escuela_que_no_existe_da_404(): void
    {
        $this->get('/escuelas/no-existe')->assertNotFound();
    }
}
