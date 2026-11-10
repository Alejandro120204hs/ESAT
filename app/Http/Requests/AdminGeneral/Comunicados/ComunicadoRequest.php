<?php

namespace App\Http\Requests\AdminGeneral\Comunicados;

use App\Enums\RolUsuario;
use App\Http\Requests\Admin\Comunicados\ComunicadoRequest as ComunicadoSedeRequest;
use App\Models\Institucional\Sede;
use App\Services\General\ComunicacionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Comunicado del Admin a todas o algunas sedes: las reglas de la secretaría (borrador, programar a futuro, un publicado no
 * cambia de destinatarios) más las sedes. Solo edita los suyos; sin adjunto (lo agrega cada sede si hace falta).
 */
class ComunicadoRequest extends ComunicadoSedeRequest
{
    public function authorize(): bool
    {
        $c = $this->route('comunicado');

        return ! $c || $c->autor?->role === RolUsuario::AdminGeneral;
    }

    protected function prepareForValidation(): void
    {
        // En pantalla "todos" es toda la sede; la fecha llega del campo datetime-local (AAAA-MM-DDTHH:MM)
        $destino = $this->input('destino');
        $this->merge([
            'destino'     => ComunicacionService::DESTINOS[$destino] ?? $destino,
            'categoria'   => $this->input('categoria') ?: 'general',
            'publicar_en' => is_string($this->input('publicar_en')) ? str_replace('T', ' ', substr($this->input('publicar_en'), 0, 16)) : null,
            'sedes'       => $this->input('sedes') === 'todas' ? Sede::pluck('id')->all() : array_values(array_unique(array_map('intval', (array) $this->input('sedes', [])))),
        ]);
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return parent::rules() + [
            'sedes'   => ['required', 'array', 'min:1'],
            'sedes.*' => ['integer', Rule::exists('sedes', 'id')],
        ];
    }

    public function after(): array
    {
        return array_merge(parent::after(), [function (Validator $v) {
            $c = $this->route('comunicado');
            if (! $c || $v->errors()->isNotEmpty() || $c->estadoActual() !== 'publicado') {
                return;
            }
            $antes = app(ComunicacionService::class)->sedesDe($c);
            sort($antes);
            $ahora = $this->input('sedes');
            sort($ahora);
            if ($antes !== $ahora) {
                $v->errors()->add('sedes', 'Ya está publicado: las sedes no se cambian. Duplícalo para enviarlo a otras sedes.');
            }
        }]);
    }

    public function messages(): array
    {
        return parent::messages() + ['sedes.required' => 'Elige al menos una sede.', 'sedes.min' => 'Elige al menos una sede.', 'sedes.*.exists' => 'Una de las sedes ya no existe.'];
    }
}
