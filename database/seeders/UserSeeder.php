<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'              => 'UFicon Admin',
                'email'             => 'info@uficon.com',
                'password'          => Hash::make('UFicon@2026!'),
                'email_verified_at' => now(),
            ],
            [
                'name'              => 'M2Dev',
                'email'             => 'dev.pongpisut@gmail.com',
                'password'          => Hash::make('UFicon@2026!'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}
