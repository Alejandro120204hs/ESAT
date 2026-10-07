<?php

namespace App\Services\Academico;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Reglas de negocio del acudiente (panel Admin › Acudientes): registrarlo con sus
 * estudiantes a cargo, editarlo, vincular/desvincular estudiantes y el estado de su cuenta.
 * Cada estudiante tiene un solo acudiente; un acudiente puede tener varios estudiantes.
 * Un menor de edad nunca puede quedar sin acudiente.
 * Las validaciones de entrada viven en los Form Requests de Admin/Acudientes.
 */
class AcudienteService
{
    /**
     * Crea la cuenta (rol acudiente, usuario = correo, clave inicial = documento)
     * y la deja como acudiente de cada estudiante de $vinculos.
     *
     * @param  array<int, array{id:int, parentesco:string}>  $vinculos
     */
    public function registrar(array $datos, array $vinculos, int $sedeId): User
    {
        return DB::transaction(function () use ($datos, $vinculos, $sedeId) {
            $acudiente = User::create($datos + [
                'role'     => RolUsuario::Acudiente,
                'sede_id'  => $sedeId,
                'password' => Hash::make($datos['numero_documento']),
                'activo'   => true,
            ]);

            foreach ($vinculos as $v) {
                $this->vincular($acudiente, User::findOrFail($v['id']), $v['parentesco']);
            }

            return $acudiente;
        });
    }

    /**
     * Guarda sus datos y deja exactamente $vinculos como sus estudiantes a cargo:
     * los que ya no están quedan sin acudiente (el Request impide quitar menores).
     */
    public function actualizar(User $acudiente, array $datos, array $vinculos): User
    {
        return DB::transaction(function () use ($acudiente, $datos, $vinculos) {
            $acudiente->update($datos);

            $ids = array_column($vinculos, 'id');
            $acudiente->estudiantesACargo()->whereNotIn('users.id', $ids)->get()
                ->each(fn (User $e) => $acudiente->estudiantesACargo()->detach($e->id));

            foreach ($vinculos as $v) {
                $this->vincular($acudiente, User::findOrFail($v['id']), $v['parentesco']);
            }

            return $acudiente;
        });
    }

    /** Deja a $acudiente como el único acudiente del estudiante (reemplaza al anterior). */
    public function vincular(User $acudiente, User $estudiante, string $parentesco): void
    {
        $estudiante->acudientes()->sync([$acudiente->id => ['parentesco' => $parentesco]]);
    }

    public function desvincular(User $acudiente, User $estudiante): void
    {
        $acudiente->estudiantesACargo()->detach($estudiante->id);
    }

    public static function esMenor(User $estudiante): bool
    {
        return $estudiante->fecha_nacimiento !== null && $estudiante->fecha_nacimiento->age < 18;
    }

    /**
     * Menores que quedarían sin acudiente si $acudiente dejara de tener a cargo
     * a todos menos $conservar (ids). Sirve para editar y desvincular.
     *
     * @param  array<int, int>  $conservar
     * @return Collection<int, User>
     */
    public function menoresQueQuedarianSolos(User $acudiente, array $conservar): Collection
    {
        return $acudiente->estudiantesACargo()->whereNotIn('users.id', $conservar)->get()
            ->filter(fn (User $e) => self::esMenor($e))
            ->values();
    }

    /** activa | sin_ingreso (nunca ha entrado) | inactiva */
    public static function estadoCuenta(User $acudiente): string
    {
        if (! $acudiente->activo) {
            return 'inactiva';
        }

        return $acudiente->ultimo_acceso ? 'activa' : 'sin_ingreso';
    }
}
