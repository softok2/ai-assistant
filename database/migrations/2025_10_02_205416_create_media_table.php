<?php

declare(strict_types=1);

use App\Enums\MediaStatus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['file', 'image', 'video', 'audio'])->default('file');
            $table->string('assistant_media_id')->nullable()->comment('Assistant media ID');
            $table->string('old_assistant_media_id')->nullable()->comment('Old assistant media ID (for removal)');
            $table->string('status', 100)->default(MediaStatus::PENDING->value);
            $table->string('name')->comment('Original media name');
            $table->integer('bytes')->nullable()->comment('Media size');
            $table->enum('purpose', ['fine-tune', 'assistants'])->default('assistants');
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'name']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
