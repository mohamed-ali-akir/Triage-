<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Technician = 'technician';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Utilisateur',
            self::Technician => 'Technicien',
            self::Admin => 'Administrateur',
        };
    }
}
