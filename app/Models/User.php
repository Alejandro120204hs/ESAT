<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RolUsuario;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'password',
        'role',
        'sede_id',
        'telefono',
        'nombres',
        'apellidos',
        'tipo_documento',
        'numero_documento',
        'fecha_nacimiento',
        'lugar_nacimiento',
        'genero',
        'direccion',
        'profesion',
        'titulo_academico',
    ];

    /**
     * "name" ya no es una columna guardada: se calcula siempre a partir
     * de nombres + apellidos, para que nunca queden desincronizados.
     * Se conserva como atributo de solo lectura por compatibilidad con
     * las vistas de Breeze (login, panel de perfil).
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => trim("{$this->nombres} {$this->apellidos}") ?: $this->email,
        );
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RolUsuario::class,
            'fecha_nacimiento' => 'date',
        ];
    }

    public function isSuperadmin(): bool
    {
        return $this->role === RolUsuario::Superadmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === RolUsuario::Admin;
    }

    public function isEstudiante(): bool
    {
        return $this->role === RolUsuario::Estudiante;
    }

    public function isDocente(): bool
    {
        return $this->role === RolUsuario::Docente;
    }

    public function isAcudiente(): bool
    {
        return $this->role === RolUsuario::Acudiente;
    }

    /**
     * A dónde va este usuario tras iniciar sesión: el panel de su
     * propio rol si ya existe, o el dashboard genérico mientras se
     * construyen los demás.
     */
    public function panelUrl(): string
    {
        return match ($this->role) {
            RolUsuario::Superadmin => route('superadmin.dashboard'),
            default => route('dashboard'),
        };
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class);
    }

    /** Programas en los que este usuario (como estudiante) está matriculado. */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'estudiante_id');
    }

    /** Cursos que este usuario dicta (como docente). */
    public function cursosComoDocente(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_docente', 'docente_id', 'curso_id');
    }

    /** Cursos en los que este usuario está inscrito (como estudiante). */
    public function cursosComoEstudiante(): BelongsToMany
    {
        return $this->belongsToMany(Curso::class, 'curso_estudiante', 'estudiante_id', 'curso_id');
    }

    /** Actividades creadas por este usuario (como docente). */
    public function actividadesCreadas(): HasMany
    {
        return $this->hasMany(Actividad::class, 'docente_id');
    }

    /** Entregas hechas por este usuario (como estudiante). */
    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'estudiante_id');
    }

    /** Evaluaciones creadas por este usuario (como docente). */
    public function evaluacionesCreadas(): HasMany
    {
        return $this->hasMany(Evaluacion::class, 'docente_id');
    }

    /** Registros de asistencia de este usuario (como estudiante). */
    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class, 'estudiante_id');
    }

    /** Estudiantes a cargo de este usuario (como acudiente), con el parentesco de cada relación. */
    public function estudiantesACargo(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'acudiente_estudiante', 'acudiente_id', 'estudiante_id')
            ->withPivot('parentesco');
    }

    /** Acudientes de este usuario (como estudiante), con el parentesco de cada relación. */
    public function acudientes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'acudiente_estudiante', 'estudiante_id', 'acudiente_id')
            ->withPivot('parentesco');
    }
}
