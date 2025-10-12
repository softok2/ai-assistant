<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignIdFor(Role::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
