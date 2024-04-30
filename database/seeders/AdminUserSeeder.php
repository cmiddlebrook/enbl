<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class AdminUserSeeder extends Seeder
{

    public function run(): void
    {
        User::create([
            'name' => 'Caroline Middlebrook',
            'email' => 'caroline.middlebrook@googlemail.com',
            'password' => bcrypt('L@stTry24'),
            'is_admin' => true
        ]);
    }
}
