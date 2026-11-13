<?php

namespace App\Enums;

enum CommercialStatus: string
{
    case New = 'new';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Active => 'Activo',
            self::Inactive => 'Inactivo',
        };
    }
}
