<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Buat Akun Manager
        User::create([
            'name' => 'Manager Kepswell',
            'email' => 'manager@kepswell.com',
            'password' => Hash::make('password123'),
            'role' => 'manager',
        ]);

        // Buat Akun Host
        User::create([
            'name' => 'Host 1',
            'email' => 'host1@kepswell.com',
            'password' => Hash::make('password123'),
            'role' => 'host',
        ]);

        User::create([
            'name' => 'Host 2',
            'email' => 'host2@kepswell.com',
            'password' => Hash::make('password123'),
            'role' => 'host',
        ]);

        // Buat 5 Aset Akun
        Asset::create([
            'name' => 'Akun Shopee 1',
            'platform' => 'Shopee',
            'credentials' => encrypt('username: shopee1, password: pass123'),
        ]);

        Asset::create([
            'name' => 'Akun Shopee 2',
            'platform' => 'Shopee',
            'credentials' => encrypt('username: shopee2, password: pass456'),
        ]);

        Asset::create([
            'name' => 'Akun Tokopedia 1',
            'platform' => 'Tokopedia',
            'credentials' => encrypt('username: tokped1, password: pass789'),
        ]);

        Asset::create([
            'name' => 'Akun TikTok Shop 1',
            'platform' => 'TikTok',
            'credentials' => encrypt('username: tiktok1, password: pass321'),
        ]);

        Asset::create([
            'name' => 'Akun TikTok Shop 2',
            'platform' => 'TikTok',
            'credentials' => encrypt('username: tiktok2, password: pass654'),
        ]);
    }
}