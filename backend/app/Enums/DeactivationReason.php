<?php

namespace App\Enums;

enum DeactivationReason: string
{
    case NonPayment = 'non_payment';
    case NoActivity = 'no_activity';
    case ClientRequest = 'client_request';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NonPayment => 'Falta de pago',
            self::NoActivity => 'Sin actividad',
            self::ClientRequest => 'Lo pidió el cliente',
            self::Other => 'Otro motivo',
        };
    }
}
