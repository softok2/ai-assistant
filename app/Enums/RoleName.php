<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case ADMIN = 'admin';
    case GOLF_MANAGER = 'golf_manager';

    case TENNIS_MANAGER = 'tennis_manager';

    case PADDLE_MANAGER = 'paddle_manager';

    case RESTAURANT_CAPTAIN = 'restaurant_captain';

    case AESTHETICS_MANAGER = 'aesthetics_manager';

    case MASSAGE_MANAGER = 'massage_manager';

    case PODIATRY_MANAGER = 'podiatry_manager';

    case GENERAL_SERVICE_MANAGER = 'general_service_manager';

    public function getName(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::GOLF_MANAGER => 'Gerente de Golf',
            self::TENNIS_MANAGER => 'Gerente de Tenis',
            self::PADDLE_MANAGER => 'Gerente de Padel',
            self::RESTAURANT_CAPTAIN => 'Capitán de Restaurante',
            self::AESTHETICS_MANAGER => 'Gerente de Estetica',
            self::MASSAGE_MANAGER => 'Gerente de Masajes',
            self::PODIATRY_MANAGER => 'Gerente de Podología',
            self::GENERAL_SERVICE_MANAGER => 'Gerente de Servicio General',
        };
    }
}
