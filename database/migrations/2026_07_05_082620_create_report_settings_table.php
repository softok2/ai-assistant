<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('report_settings');

        Schema::create('report_settings', function (Blueprint $table) {
            $table->id();
            $table->string('club_name')->unique();
            $table->boolean('enabled')->default(false);
            $table->json('recipients');
            $table->string('frequency')->default('weekly');
            $table->unsignedTinyInteger('day_of_week')->default(1);
            $table->unsignedTinyInteger('hour')->default(8);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_settings');
    }
};
