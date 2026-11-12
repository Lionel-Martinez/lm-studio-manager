<?php

namespace App\Enums;

enum ClientType: string
{
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Persona',
            self::Company => 'Empresa o negocio',
        };
    }
}
