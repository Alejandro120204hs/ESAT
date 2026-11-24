<?php

namespace Tests\Feature\Sitio;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sitio público: cada programa y escuela
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

    public function test_la_galeria_muestra_las_fotos_reales_del_programa_con_su_descripcion(): void
    {
        $conGaleria = array_filter(config('contenido.programas'), fn ($p) => ! empty($p['galeria']));
        $this->assertNotEmpty($conGaleria);

        foreach ($conGaleria as $slug => $p) {
            $respuesta = $this->get(route('programas.' . $slug))->assertOk()->assertSee('Nuestros estudiantes');
            foreach ($p['galeria'] as [$archivo, $descripcion]) {
                $base = pathinfo($archivo, PATHINFO_FILENAME);
                $this->assertFileExists(public_path("assets/img/$base.webp"), "Falta la foto $base de $slug");
                $this->assertFileExists(public_path("assets/img/$base-960.webp"), "Falta la versión de 960 px de $base");
                $respuesta->assertSee('alt="' . e($descripcion) . '"', false)->assertSee("$base.webp");
            }
        }

        // Un programa sin fotos propias no muestra la sección vacía
        $this->get(route('programas.titiritero'))->assertOk()->assertDontSee('sub-galeria');
    }

    public function test_cada_programa_esta_en_una_escuela_y_su_ruta_es_la_misma(): void
    {
        $enEscuelas = collect(config('sitio.escuelas'))->flatMap(fn ($e) => array_column($e['programas'], 1))->sort()->values()->all();
        $conPagina = collect(config('contenido.programas'))->keys()->sort()->values()->all();

        $this->assertSame($conPagina, $enEscuelas);
        $this->assertSame(url('/barberia'), route('programas.barberia'));
    }

    public function test_educacion_continuada_ya_no_esta_en_el_sitio(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('Educación Continuada');
        $this->get(route('programas.barberia'))->assertOk()->assertDontSee('Educación Continuada');
        $this->get('/salud-ocupacional')->assertStatus(301)->assertRedirect('/');
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

    public function test_el_inicio_y_el_footer_nombran_las_sedes_del_afiche(): void
    {
        $sedes = config('sitio.sedes');
        $this->assertSame(['Sasaima', 'Guaduas', 'La Dorada', 'Marquetalia'], array_column($sedes, 'nombre'));

        $inicio = $this->get(route('home'))->assertOk()
            ->assertSee('Ocho escuelas, cuatro sedes, un mismo propósito')
            ->assertSee('Cuatro sedes en Cundinamarca y Caldas')
            ->assertDontSee('franquicia');
        // Cada sede con su teléfono, y "Escribir" va a su propio WhatsApp
        foreach ($sedes as $sede) {
            $inicio->assertSee($sede['nombre'])->assertSee($sede['telefono'][1])
                ->assertSee('https://wa.me/' . $sede['telefono'][0], false);
        }
        $this->get(route('escuelas.' . config('sitio.escuelas.0.slug')))
            ->assertSee('Marquetalia')->assertDontSee('Bogotá')->assertDontSee('franquicia');
    }

    public function test_las_direcciones_de_antes_del_catalogo_nuevo_llevan_a_su_pagina_de_ahora(): void
    {
        foreach (config('sitio.redirecciones') as $antes => $ahora) {
            $this->get('/' . $antes)->assertStatus(301)->assertRedirect('/' . $ahora);
            $this->get('/' . $ahora)->assertOk();
        }
        $this->get('/cocina-nacional-e-internacional')->assertRedirect(route('programas.chef-de-alta-cocina'));
    }

    public function test_una_escuela_que_no_existe_da_404(): void
    {
        $this->get('/escuelas/no-existe')->assertNotFound();
    }
}
