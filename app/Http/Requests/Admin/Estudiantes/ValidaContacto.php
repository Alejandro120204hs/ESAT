<?php

namespace App\Http\Requests\Admin\Estudiantes;

use App\Models\Academico\ContactoEmergencia;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Contacto de emergencia del estudiante (obligatorio para todos, decidido con el usuario el 25-11-2026): nombre, celular
 * y parentesco. Llega como contacto[nombre|celular|parentesco] al registrar y al editar.
 */
trait ValidaContacto
{
    protected function limpiarContacto(): void
    {
        $c = (array) $this->input('contacto', []);
        $this->merge(['contacto' => [
            'nombre'     => trim(preg_replace('/\s+/', ' ', (string) ($c['nombre'] ?? ''))),
            'celular'    => preg_replace('/\D/', '', (string) ($c['celular'] ?? '')),
            'parentesco' => trim((string) ($c['parentesco'] ?? '')),
        ]]);
    }

    protected function reglasContacto(): array
    {
        return [
            'contacto'            => ['required', 'array'],
            'contacto.nombre'     => ['required', 'string', 'min:3', 'max:120'],
            'contacto.celular'    => ['required', 'regex:/^3\d{9}$/'],
            'contacto.parentesco' => ['required', Rule::in(ContactoEmergencia::PARENTESCOS)],
        ];
    }

    /** El contacto no puede ser el mismo estudiante (su propio celular no sirve en una emergencia). */
    protected function validarContacto(Validator $v, ?string $celularEstudiante): void
    {
        if ($celularEstudiante && $this->input('contacto.celular') === preg_replace('/\D/', '', $celularEstudiante)) {
            $v->errors()->add('contacto.celular', 'El celular del contacto de emergencia debe ser distinto al del estudiante.');
        }
    }

    /** Lo que guarda EstudianteService::guardarContacto. */
    public function datosContacto(): array
    {
        return $this->input('contacto');
    }

    protected function atributosContacto(): array
    {
        return [
            'contacto.nombre'     => 'nombre del contacto de emergencia',
            'contacto.celular'    => 'celular del contacto de emergencia',
            'contacto.parentesco' => 'parentesco del contacto de emergencia',
        ];
    }

    protected function mensajesContacto(): array
    {
        return [
            'contacto.required'            => 'Registra el contacto de emergencia del estudiante.',
            'contacto.nombre.required'     => 'Escribe el nombre del contacto de emergencia.',
            'contacto.nombre.min'          => 'Escribe el nombre completo del contacto de emergencia.',
            'contacto.celular.required'    => 'Escribe el celular del contacto de emergencia.',
            'contacto.celular.regex'       => 'El celular del contacto de emergencia debe tener 10 dígitos y empezar por 3.',
            'contacto.parentesco.required' => 'Selecciona el parentesco del contacto de emergencia.',
            'contacto.parentesco.in'       => 'Selecciona un parentesco de la lista.',
        ];
    }
}
