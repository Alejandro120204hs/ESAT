<?php

namespace Database\Factories;

use App\Enums\RolUsuario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // "name" no es columna: se arma con nombres + apellidos (ver User::name())
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName().' '.fake()->lastName(),
            'tipo_documento' => 'CC',
            'numero_documento' => (string) fake()->unique()->numberBetween(10000000, 1099999999),
            'role' => 'estudiante',
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /** Todo estudiante tiene contacto de emergencia (obligatorio): sin él no se matricula. */
    public function configure(): static
    {
        return $this->afterCreating(function (User $u) {
            if ($u->role === RolUsuario::Estudiante && ! $u->contactoEmergencia()->exists()) {
                $u->contactoEmergencia()->create([
                    'nombre'     => fake()->firstName().' '.fake()->lastName(),
                    'celular'    => '3'.fake()->numerify('#########'),
                    'parentesco' => 'Madre',
                ]);
            }
        });
    }

    /** Un estudiante sin contacto de emergencia (para probar que sin él no se matricula). */
    public function sinContacto(): static
    {
        return $this->afterCreating(fn (User $u) => $u->contactoEmergencia()->delete());
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
