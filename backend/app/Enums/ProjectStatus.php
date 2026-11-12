<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Prospect = 'prospect';
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospecto',
            self::Waiting => 'En espera',
            self::InProgress => 'En desarrollo',
            self::Ready => 'Por entregar',
            self::Delivered => 'Entregado',
        };
    }
}
