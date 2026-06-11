<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the default backoffice user for local/demo environments.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'roger@idronlex.com')],
            [
                'name' => env('ADMIN_NAME', 'Roger'),
                'password' => env('ADMIN_PASSWORD', '12345678'),
                'role' => User::ROLE_GESTOR,
                'email_verified_at' => now(),
            ],
        );
    }
}
