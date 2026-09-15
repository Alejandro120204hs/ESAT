<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Estudiante = 'estudiante';
    case Docente = 'docente';
    case Acudiente = 'acudiente';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Admin ESAT',
            self::Estudiante => 'Estudiante',
            self::Docente => 'Docente',
            self::Acudiente => 'Acudiente',
        };
    }
}
