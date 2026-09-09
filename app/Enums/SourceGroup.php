<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Áreas del club que agrupan los documentos que consulta el asistente. El
 * grupo lo pone el import de Pentaho (prefijo del nombre del archivo) o el
 * administrador al subir un documento a mano, así que puede llegar uno que no
 * esté en esta lista.
 */
enum SourceGroup: string
{
    case Golf = 'golf';
    case Tennis = 'tennis';
    case Paddle = 'paddle';
    case Restaurant = 'restaurant';
    case Services = 'services';
    case Massage = 'massage';
    case Aesthetic = 'aesthetic';
    case Experience = 'experience';

    /**
     * Nombre legible de un grupo cualquiera: los conocidos llevan su etiqueta
     * en español y el resto se muestra tal cual, solo con formato de título.
     */
    public static function labelFor(?string $group): string
    {
        $group = (string) $group;

        return self::tryFrom($group)?->label() ?? Str::headline($group);
    }

    public function label(): string
    {
        return match ($this) {
            self::Golf => 'Golf',
            self::Tennis => 'Tenis',
            self::Paddle => 'Pádel',
            self::Restaurant => 'Restaurante',
            self::Services => 'Servicios',
            self::Massage => 'Masaje',
            self::Aesthetic => 'Estética',
            self::Experience => 'Experiencia',
        };
    }
}
