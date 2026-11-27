<?php

namespace App\Enums;

enum RolUsuario: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case AdminGeneral = 'admin_general';
    case Estudiante = 'estudiante';
    case Docente = 'docente';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Administrativo',
            self::AdminGeneral => 'Admin',
            self::Estudiante => 'Estudiante',
            self::Docente => 'Docente',
        };
    }
}
