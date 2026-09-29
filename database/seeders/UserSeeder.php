<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'user2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'user3',
            'email' => 'user3@example.com',
            'password' => Hash::make('password'),
            'admin_status' => true,
        ]);
    }
}
