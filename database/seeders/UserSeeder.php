<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        User::updateOrCreate(
    ['email' => env('ADMIN_MAIL')],
    [
        'name' => 'Admin',
        'password' => bcrypt(env('ADMIN_PASSWORD')),
        'role' => 1,
        'prenom' => 'admin',
    ]
);
    }
}
