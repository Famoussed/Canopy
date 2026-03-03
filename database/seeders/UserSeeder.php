<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        User::factory()->superAdmin()->create([
            'name' => 'Super Admin',
            'email' => 'admin@canopy.dev',
        ]);

        // Demo Owner
        User::factory()->create([
            'name' => 'Selim Dev',
            'email' => 'selim@canopy.dev',
        ]);

        // Demo Members
        User::factory()->count(5)->create();
    }
}
