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

    case RESTAURANT_MANAGER = 'restaurant_manager';

    case FUTBOL_MANAGER = 'futbol_manager';

    case INCIDENCES_MANAGER = 'incidences_manager';

    case GUESTS_MANAGER = 'guests_manager';

    case WELLNESS_MANAGER = 'wellness_manager';

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
            self::RESTAURANT_MANAGER => 'Gerencia de restaurante',
            self::FUTBOL_MANAGER => 'Gerencia de fútbol',
            self::INCIDENCES_MANAGER => 'Gerencia de incidencias',
            self::GUESTS_MANAGER => 'Gerencia de invitados',
            self::WELLNESS_MANAGER => 'Gerencia de bienestar',
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
                'Fútbol',
                'Incidencias',
                'Invitados',
                'Bienestar',
                'Estética',
                'Masajes',
                'Podología',
                'Servicios generales',
            ],
            self::GOLF_MANAGER => ['Golf'],
            self::TENNIS_MANAGER => ['Tenis'],
            self::PADDLE_MANAGER => ['Pádel'],
            self::RESTAURANT_CAPTAIN => ['Restaurantes'],
            self::RESTAURANT_MANAGER => ['Restaurantes'],
            self::FUTBOL_MANAGER => ['Fútbol'],
            self::INCIDENCES_MANAGER => ['Incidencias'],
            self::GUESTS_MANAGER => ['Invitados'],
            self::WELLNESS_MANAGER => ['Bienestar'],
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
            self::RESTAURANT_MANAGER => 'Gerente de Restaurante',
            self::FUTBOL_MANAGER => 'Gerente de Fútbol',
            self::INCIDENCES_MANAGER => 'Gerente de Incidencias',
            self::GUESTS_MANAGER => 'Gerente de Invitados',
            self::WELLNESS_MANAGER => 'Gerente de Bienestar',
            self::AESTHETICS_MANAGER => 'Gerente de Estetica',
            self::MASSAGE_MANAGER => 'Gerente de Masajes',
            self::PODIATRY_MANAGER => 'Gerente de Podología',
            self::GENERAL_SERVICE_MANAGER => 'Gerente de Servicio General',
        };
    }

    /**
     * Grupos de fuentes a los que se recorta la búsqueda del asistente. Vacío
     * significa "todo el store del club": la dirección general y el rol
     * multi-área que manda vallealto (`general_service_manager`) no se
     * recortan. Estética, masaje y podología viven hoy dentro del documento de
     * bienestar; llevan además su grupo viejo mientras ccm siga en Pentaho.
     *
     * @return array<int, string>
     */
    public function sourceGroups(): array
    {
        return match ($this) {
            self::ADMIN, self::GENERAL_SERVICE_MANAGER => [],
            self::GOLF_MANAGER => ['golf'],
            self::TENNIS_MANAGER => ['tennis'],
            self::PADDLE_MANAGER => ['paddle'],
            self::RESTAURANT_MANAGER, self::RESTAURANT_CAPTAIN => ['restaurant'],
            self::FUTBOL_MANAGER => ['futbol'],
            self::INCIDENCES_MANAGER => ['incidences'],
            self::GUESTS_MANAGER => ['guests'],
            self::WELLNESS_MANAGER, self::PODIATRY_MANAGER => ['wellness'],
            self::AESTHETICS_MANAGER => ['wellness', 'aesthetic'],
            self::MASSAGE_MANAGER => ['wellness', 'massage'],
        };
    }
}
