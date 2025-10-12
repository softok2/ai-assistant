<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;

final class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::firstOrCreate(['name' => $role->value]);
        }
    }
}
