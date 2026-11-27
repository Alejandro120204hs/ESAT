<?php

namespace App\Http\Requests\Docente\Comunicados;

use App\Http\Requests\Admin\Comunicados\ComunicadoRequest as ComunicadoSecretariaRequest;
use App\Models\Comunicacion\Comunicado;
use App\Services\Comunicacion\ComunicadoDocenteService;
use Illuminate\Validation\Validator;

/**
 * Crear o editar un comunicado del docente a uno o varios de sus grupos: las reglas de la secretaría (accion borrador |
 * publicar | programar, título, categoría, mensaje, adjunto PDF/JPG/PNG ≤ 5 MB, fecha futura al programar, "visible
 * hasta") con grupos en vez de destino. Solo los suyos y, al agregarlos, no finalizados. Un publicado no cambia de grupos
 * ni vuelve a borrador; uno archivado no se edita. Solo edita sus propios comunicados.
 */
class ComunicadoRequest extends ComunicadoSecretariaRequest
{
    public function authorize(): bool
    {
        $c = $this->route('comunicado');

        return ! $c || ($c->autor_id === $this->user()->id && $c->destino === 'grupo');
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $grupos = $this->input('grupos');
        $this->merge([
            'grupos' => is_array($grupos) ? array_values(array_unique(array_map('intval', array_filter($grupos, 'is_numeric')))) : $grupos,
            'fijado' => false,
        ]);
    }

    public function rules(): array
    {
        $reglas = parent::rules();
        unset($reglas['destino'], $reglas['fijado']);

        return $reglas + [
            'grupos'   => ['required', 'array', 'min:1'],
            'grupos.*' => ['integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $servicio = app(ComunicadoDocenteService::class);
            $suyos = $servicio->grupos($this->user());
            $c = $this->route('comunicado');
            $actuales = [];

            if ($c) {
                $filas = $servicio->lote($c);
                $vivas = $filas->reject(fn (Comunicado $f) => $f->estadoActual() === 'archivado');
                $actuales = $filas->pluck('curso_id')->all();
                if ($vivas->isEmpty()) {
                    $v->errors()->add('titulo', 'Un comunicado archivado no se edita.');

                    return;
                }
                if ($vivas->contains(fn (Comunicado $f) => $f->estadoActual() === 'publicado')) {
                    if ($this->input('accion') !== 'publicar') {
                        $v->errors()->add('titulo', 'Un comunicado publicado no vuelve a borrador ni se programa.');
                    } elseif (collect($this->grupos())->sort()->values()->all() !== collect($actuales)->sort()->values()->all()) {
                        $v->errors()->add('grupos', 'Ya está publicado: los grupos no se cambian. Publica otro comunicado para los demás grupos.');
                    }

                    return;
                }
            }

            // Los grupos que agrega: suyos y sin finalizar (los que ya tenía el borrador pueden quedarse)
            foreach (array_diff($this->grupos(), $actuales) as $id) {
                $g = $suyos->firstWhere('id', $id);
                if (! $g) {
                    $v->errors()->add('grupos', 'Elige solo grupos que tengas a cargo.');

                    return;
                }
                if ($g->estado === 'finalizado') {
                    $v->errors()->add('grupos', "El {$g->nombre} ya finalizó: no recibe comunicados nuevos.");

                    return;
                }
            }

            // "Visible hasta" no puede ser antes de que se publique
            $desde = $this->input('accion') === 'programar' ? substr((string) $this->input('publicar_en'), 0, 10) : now()->toDateString();
            if ($this->input('visible_hasta') && $this->input('visible_hasta') < $desde) {
                $v->errors()->add('visible_hasta', '"Visible hasta" no puede ser antes de que se publique.');
            }
        }];
    }

    public function datos(): array
    {
        return $this->safe()->only(['titulo', 'categoria', 'mensaje', 'publicar_en', 'visible_hasta']);
    }

    /** @return int[] */
    public function grupos(): array
    {
        return (array) $this->input('grupos', []);
    }

    public function messages(): array
    {
        return parent::messages() + [
            'grupos.required' => 'Elige al menos un grupo.',
            'grupos.array'    => 'Elige al menos un grupo.',
            'grupos.min'      => 'Elige al menos un grupo.',
            'grupos.*'        => 'Elige solo grupos que tengas a cargo.',
        ];
    }
}
