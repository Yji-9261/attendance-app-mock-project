<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            "name" => "管理者",
            "email" => "admin@test.com",
            "password" => Hash::make("password"),
            "admin_status" => true,
        ]);

        User::factory()->create([
            "name" => "田中",
            "email" => "x@test.com",
            "password" => Hash::make("password"),
        ]);
    }
}
