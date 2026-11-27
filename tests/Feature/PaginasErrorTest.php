<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Las páginas de error salen en español y llevan a donde corresponde (auditoría 26-11-2026). */
class PaginasErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_sesion_el_404_esta_en_espanol_y_lleva_al_inicio(): void
    {
        $this->get('/esta-direccion-no-existe')->assertNotFound()
            ->assertSee('No encontramos esta página')->assertSee('Ir al inicio')
            ->assertDontSee('Not Found');
    }

    public function test_con_sesion_lleva_al_panel_de_su_rol(): void
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);

        $this->actingAs($estudiante)->get('/admin')->assertForbidden()
            ->assertSee('No tienes permiso para ver esto')->assertSee('Ir a mi panel')
            ->assertSee($estudiante->panelUrl(), false);
    }

    public function test_cada_codigo_tiene_su_pagina(): void
    {
        foreach ([419 => 'Tu sesión venció', 429 => 'Demasiados intentos seguidos', 500 => 'Algo salió mal de nuestro lado', 503 => 'Estamos haciendo mantenimiento'] as $codigo => $titulo) {
            $html = view("errors.{$codigo}")->render();
            $this->assertStringContainsString($titulo, $html);
            $this->assertStringContainsString('lang="es"', $html);
        }
        $this->assertStringContainsString(route('login'), view('errors.419')->render());
    }
}
