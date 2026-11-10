<?php

namespace App\Http\Requests\AdminGeneral\Foro;

use App\Http\Requests\Admin\Foro\TemaRequest as SedeTemaRequest;
use App\Models\Comunicacion\ForoTema;
use Illuminate\Validation\Rule;

/** Abrir un tema en el foro de la sede que se elija, con las reglas de la secretaría. La categoría llega con su nombre. */
class TemaRequest extends SedeTemaRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categoria = $this->input('categoria');
        $this->merge(['categoria' => array_search($categoria, ForoTema::CATEGORIAS, true) ?: $categoria]);
        parent::prepareForValidation();
    }

    public function rules(): array
    {
        return parent::rules() + ['sede_id' => ['required', 'integer', Rule::exists('sedes', 'id')]];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['sede_id' => 'sede'];
    }
}
