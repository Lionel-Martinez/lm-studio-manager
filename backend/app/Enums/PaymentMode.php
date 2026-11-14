<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Single = 'single';
    case Split = 'split';
    case Installments = 'installments';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Pago único',
            self::Split => 'Anticipo y saldo',
            self::Installments => 'En cuotas',
        };
    }
}
