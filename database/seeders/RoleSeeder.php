<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superadmin', 'admin', 'lecturer', 'student'] as $name) {
            Role::query()->firstOrCreate(['name' => $name], ['description' => ucfirst($name).' role']);
        }
    }
}
