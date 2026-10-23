<?php

declare(strict_types=1);

use App\Models\File;
use App\Enums\MediaStatus;
use App\Models\VectorStore;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\UniqueConstraintViolationException;

it('creates the vector_stores table with a unique key per club, environment and provider', function (): void {
    expect(Schema::hasColumns('vector_stores', ['club', 'environment', 'provider', 'store_id', 'name']))->toBeTrue();

    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai']);
    VectorStore::factory()->create(['club' => 'ccm', 'environment' => 'testing', 'provider' => 'openai']);
})->throws(UniqueConstraintViolationException::class);

it('sends indexed files back to pending so they are re-uploaded to the new store, leaving expired ones alone', function (): void {
    $indexed = File::factory()->completed()->create();
    $failed = File::factory()->failed()->create(['assistant_media_id' => 'file-old']);
    $expired = File::factory()->completed()->expired()->create();

    Schema::drop('vector_stores');
    $migration = include base_path('database/migrations/2026_10_22_120000_create_vector_stores_table.php');
    $migration->up();

    expect($indexed->fresh())->status->toBe(MediaStatus::PENDING)
        ->assistant_media_id->toBeNull()->synced_at->toBeNull()->bytes->toBeNull()
        ->and($failed->fresh())->status->toBe(MediaStatus::PENDING)->assistant_media_id->toBeNull()
        ->and($expired->fresh())->status->toBe(MediaStatus::COMPLETED)->assistant_media_id->not->toBeNull();
});
