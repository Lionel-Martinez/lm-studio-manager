<?php

namespace App\Enums;

enum ServiceType: string
{
    case MobileApp = 'mobile_app';
    case WebApp = 'web_app';
    case DigitalMenu = 'digital_menu';
    case StaticSite = 'static_site';

    public function label(): string
    {
        return match ($this) {
            self::MobileApp => 'Aplicación móvil',
            self::WebApp => 'Aplicación web',
            self::DigitalMenu => 'Menú digital',
            self::StaticSite => 'Página web estática',
        };
    }
}
