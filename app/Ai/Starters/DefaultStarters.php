<?php

declare(strict_types=1);

namespace App\Ai\Starters;

use App\Enums\RoleName;

/**
 * Preguntas de arranque fijas por rol. Se usan cuando el sugeridor falla o
 * cuando la biblioteca todavía no tiene documentos que citar.
 */
final class DefaultStarters
{
    /**
     * @return array<int, array{area: string, question: string}>
     */
    public static function for(?RoleName $role): array
    {
        return match ($role) {
            RoleName::GOLF_MANAGER => [
                ['area' => 'Golf', 'question' => '¿Cómo va la ocupación del campo esta semana?'],
                ['area' => 'Golf', 'question' => '¿Qué horarios de salida se quedan vacíos?'],
                ['area' => 'Golf', 'question' => '¿Cuántas reservas terminaron en no-show?'],
                ['area' => 'Golf', 'question' => 'Compara las salidas de este mes con el anterior'],
            ],
            RoleName::TENNIS_MANAGER => [
                ['area' => 'Tenis', 'question' => '¿Cómo va la ocupación de las canchas?'],
                ['area' => 'Tenis', 'question' => '¿Qué horarios tienen más demanda?'],
                ['area' => 'Tenis', 'question' => '¿Cuántas clases se impartieron esta semana?'],
                ['area' => 'Tenis', 'question' => 'Compara la ocupación contra el mes pasado'],
            ],
            RoleName::PADDLE_MANAGER => [
                ['area' => 'Pádel', 'question' => '¿Cómo va la ocupación de las canchas de pádel?'],
                ['area' => 'Pádel', 'question' => '¿Qué horarios se llenan primero?'],
                ['area' => 'Pádel', 'question' => '¿Cuántas reservas se cancelaron esta semana?'],
                ['area' => 'Pádel', 'question' => 'Compara la demanda de fin de semana con la de entre semana'],
            ],
            RoleName::RESTAURANT_CAPTAIN => [
                ['area' => 'Restaurantes', 'question' => '¿Cómo va la ocupación de los turnos de comida?'],
                ['area' => 'Restaurantes', 'question' => '¿Qué días tuvieron más reservas sin presentarse?'],
                ['area' => 'Restaurantes', 'question' => '¿Cuál es el consumo promedio por mesa?'],
                ['area' => 'Restaurantes', 'question' => 'Compara la semana con la anterior'],
            ],
            RoleName::AESTHETICS_MANAGER => [
                ['area' => 'Estética', 'question' => '¿Cómo va la agenda de estética esta semana?'],
                ['area' => 'Estética', 'question' => '¿Qué servicios se piden más?'],
                ['area' => 'Estética', 'question' => '¿Cuántas citas quedaron sin atender?'],
                ['area' => 'Estética', 'question' => 'Compara los ingresos contra el mes pasado'],
            ],
            RoleName::MASSAGE_MANAGER => [
                ['area' => 'Masajes', 'question' => '¿Cómo va la ocupación de las cabinas?'],
                ['area' => 'Masajes', 'question' => '¿Qué horarios quedan libres con más frecuencia?'],
                ['area' => 'Masajes', 'question' => '¿Cuántas citas se cancelaron esta semana?'],
                ['area' => 'Masajes', 'question' => 'Compara la demanda con la del mes anterior'],
            ],
            RoleName::PODIATRY_MANAGER => [
                ['area' => 'Podología', 'question' => '¿Cómo va la agenda de podología?'],
                ['area' => 'Podología', 'question' => '¿Cuántas citas nuevas hubo esta semana?'],
                ['area' => 'Podología', 'question' => '¿Qué tratamientos se repiten más?'],
                ['area' => 'Podología', 'question' => 'Compara la ocupación contra el mes pasado'],
            ],
            RoleName::GENERAL_SERVICE_MANAGER => [
                ['area' => 'Servicios generales', 'question' => '¿Qué incidencias siguen abiertas?'],
                ['area' => 'Servicios generales', 'question' => '¿Cuánto tardamos en cerrar un reporte?'],
                ['area' => 'Servicios generales', 'question' => '¿Qué áreas generan más mantenimiento?'],
                ['area' => 'Servicios generales', 'question' => 'Compara los reportes de este mes con el anterior'],
            ],
            default => [
                ['area' => 'Dirección', 'question' => 'Dame un resumen ejecutivo de la semana'],
                ['area' => 'Golf', 'question' => '¿Cómo va la ocupación del campo?'],
                ['area' => 'Restaurantes', 'question' => '¿Cómo se comportaron las reservas de restaurante?'],
                ['area' => 'Dirección', 'question' => '¿Qué área necesita atención esta semana?'],
            ],
        };
    }
}
