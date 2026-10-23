<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VectorStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VectorStore>
 */
final class VectorStoreFactory extends Factory
{
    protected $model = VectorStore::class;

    public function definition(): array
    {
        return [
            'club' => 'ccm',
            'environment' => app()->environment(),
            'provider' => 'openai',
            'store_id' => 'vs_'.fake()->unique()->lexify('????????????????'),
            'name' => 'ccm-assistant-'.app()->environment(),
        ];
    }
}
