<?php

declare(strict_types=1);

namespace App\Enums;

enum ClubName: string
{
    case CCM = 'ccm';

    case VALLEALTO = 'vallealto';

    case TERRALTA = 'terralta';

    case HERRADURA = 'herradura';

    public function getName(): string
    {
        return match ($this) {
            self::CCM => 'Club Campestre de Monterrey',
            self::VALLEALTO => 'Club Valle Alto',
            self::TERRALTA => 'Club Terralta',
            self::HERRADURA => 'Club La Herradura',
        };
    }
}
