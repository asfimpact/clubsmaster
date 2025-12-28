<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@clubmaster.com'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => 'admin123', // Automatically hashed by the User model's 'hashed' cast
                'role' => 'admin',
            ]
        );
    }
}
