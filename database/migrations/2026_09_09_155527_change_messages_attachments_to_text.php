<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * `attachments` guarda el JSON de los adjuntos del mensaje. Como varchar(255)
 * cabía un solo archivo; con tres ya fallaba con "Data too long".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->text('attachments')->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->string('attachments')->change();
        });
    }
};
