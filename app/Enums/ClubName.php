<?php

declare(strict_types=1);

namespace App\Enums;

enum ClubName: string
{
    case CCM = 'ccm';

    case VALLEALTO = 'vallealto';

    case TERRALTA = 'terralta';

    case HERRADURA = 'herradura';

    case SALTILLO = 'saltillo';

    /**
     * Nombre del club tal como debe aparecer en la interfaz y en el contexto
     * del agente.
     */
    public function label(): string
    {
        return match ($this) {
            self::CCM => 'Club Campestre Monterrey',
            self::VALLEALTO => 'Club Valle Alto',
            self::TERRALTA => 'Club Terralta',
            self::HERRADURA => 'Club La Herradura',
            self::SALTILLO => 'Club Campestre Saltillo',
        };
    }

    public function getName(): string
    {
        return match ($this) {
            self::CCM => 'Club Campestre de Monterrey',
            self::VALLEALTO => 'Club Valle Alto',
            self::TERRALTA => 'Club Terralta',
            self::HERRADURA => 'Club La Herradura',
            self::SALTILLO => 'Club Campestre Saltillo',
        };
    }
}
