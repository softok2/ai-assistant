<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClubName;
use App\Enums\ReportFrequency;
use Illuminate\Database\Eloquent\Model;

final class ReportSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'recipients' => 'array',
            'frequency' => ReportFrequency::class,
            'last_sent_at' => 'datetime',
        ];
    }

    public static function forClub(ClubName|string $club): self
    {
        $value = $club instanceof ClubName ? $club->value : $club;

        return self::firstOrNew(['club_name' => $value]);
    }

    /**
     * @return array<int, string>
     */
    public function validRecipients(): array
    {
        return collect($this->recipients ?? [])
            ->filter(fn ($email) => is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values()
            ->all();
    }
}
