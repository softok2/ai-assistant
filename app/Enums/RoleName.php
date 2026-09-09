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

    /**
     * Nombre legible del rol para la interfaz y el contexto del agente.
     */
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Dirección general',
            self::GOLF_MANAGER => 'Gerencia de golf',
            self::TENNIS_MANAGER => 'Gerencia de tenis',
            self::PADDLE_MANAGER => 'Gerencia de pádel',
            self::RESTAURANT_CAPTAIN => 'Capitanía de restaurante',
            self::AESTHETICS_MANAGER => 'Gerencia de estética',
            self::MASSAGE_MANAGER => 'Gerencia de masajes',
            self::PODIATRY_MANAGER => 'Gerencia de podología',
            self::GENERAL_SERVICE_MANAGER => 'Gerencia de servicios generales',
        };
    }

    /**
     * Áreas del club que atiende el rol. La dirección general las atiende
     * todas; cada gerencia solo la suya.
     *
     * @return array<int, string>
     */
    public function areas(): array
    {
        return match ($this) {
            self::ADMIN => [
                'Golf',
                'Tenis',
                'Pádel',
                'Restaurantes',
                'Estética',
                'Masajes',
                'Podología',
                'Servicios generales',
            ],
            self::GOLF_MANAGER => ['Golf'],
            self::TENNIS_MANAGER => ['Tenis'],
            self::PADDLE_MANAGER => ['Pádel'],
            self::RESTAURANT_CAPTAIN => ['Restaurantes'],
            self::AESTHETICS_MANAGER => ['Estética'],
            self::MASSAGE_MANAGER => ['Masajes'],
            self::PODIATRY_MANAGER => ['Podología'],
            self::GENERAL_SERVICE_MANAGER => ['Servicios generales'],
        };
    }

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
