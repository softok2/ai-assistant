<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\File;
use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
final class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $group = fake()->randomElement(['golf', 'tennis', 'paddle', 'restaurant']);
        $stamp = fake()->unique()->numberBetween(1_780_000_000, 1_790_000_000);

        return [
            'project' => 'ccm',
            'group' => $group,
            'name' => "{$group}-output-{$stamp}.md",
            'status' => MediaStatus::PENDING,
            'assistant_media_id' => null,
            'bytes' => null,
            'synced_at' => null,
            'expired_at' => null,
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes) => [
            'status' => MediaStatus::COMPLETED,
            'assistant_media_id' => 'file-'.fake()->unique()->lexify('??????????'),
            'bytes' => fake()->numberBetween(10_000, 40_000),
            'synced_at' => now()->subHour(),
        ]);
    }

    public function failed(): self
    {
        return $this->state(fn () => ['status' => MediaStatus::FAILED]);
    }

    public function expired(): self
    {
        return $this->state(fn () => ['expired_at' => now()->subMinute()]);
    }
}
