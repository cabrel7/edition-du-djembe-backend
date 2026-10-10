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
        $email = env('ADMIN_MAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        if (!env('ADMIN_MAIL')) {
            $this->command?->warn('ADMIN_MAIL absent du .env — compte admin créé avec ' . $email . ' / ' . $password);
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => bcrypt($password),
                'role' => 1,
                'prenom' => 'admin',
            ]
        );
    }
}
