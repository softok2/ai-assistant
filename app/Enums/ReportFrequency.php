<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

enum ReportFrequency: string
{
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Semanal',
            self::Biweekly => 'Quincenal',
            self::Monthly => 'Mensual',
        };
    }

    /**
     * Human period covered by a report generated on the given date.
     */
    public function periodLabel(CarbonInterface $on): string
    {
        return match ($this) {
            self::Weekly, self::Biweekly => $on->copy()->subDays($this === self::Weekly ? 7 : 14)->translatedFormat('d MMM')
                .' – '.$on->copy()->subDay()->translatedFormat('d MMM Y'),
            self::Monthly => $on->copy()->subMonthNoOverflow()->translatedFormat('MMMM Y'),
        };
    }

    /**
     * Whether a report with this cadence is due on the given datetime,
     * considering the configured weekday/day-of-month, hour and last send.
     */
    public function isDue(CarbonInterface $now, int $day, int $hour, ?CarbonInterface $lastSentAt): bool
    {
        if ($now->hour < $hour) {
            return false;
        }

        $matchesDay = $this === self::Monthly
            ? $now->day === max(1, min($day, $now->daysInMonth))
            : $now->dayOfWeek === $day;

        if (! $matchesDay) {
            return false;
        }

        if ($lastSentAt === null) {
            return true;
        }

        $minGapDays = match ($this) {
            self::Weekly => 6,
            self::Biweekly => 13,
            self::Monthly => 27,
        };

        return $lastSentAt->diffInDays($now) >= $minGapDays;
    }
}
