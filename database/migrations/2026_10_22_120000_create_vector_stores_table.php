<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vector_stores', function (Blueprint $table): void {
            $table->id();
            $table->string('club', 20);
            $table->string('environment', 20);
            $table->string('provider', 40);
            $table->string('store_id', 80);
            $table->string('name', 120);
            $table->timestamps();

            $table->unique(['club', 'environment', 'provider'], 'vector_stores_club_environment_provider_unique');
        });

        // Los ids de archivo guardados apuntan al store anterior (el del .env).
        // Los vigentes vuelven a pendiente para que la siguiente sincronización
        // los suba al store nuevo; los caducados se dejan como están.
        DB::table('files')->whereNull('expired_at')->update([
            'status' => 'pending',
            'assistant_media_id' => null,
            'synced_at' => null,
            'bytes' => null,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('vector_stores');
    }
};
