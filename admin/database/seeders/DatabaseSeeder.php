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
     *
     * The first manager has to come from here: you cannot create it through a
     * screen that only a manager may open. The worker exists so the 403 path
     * can be demonstrated without hand-editing the database.
     */
    public function run(): void
    {
        // updateOrCreate keys on the email, so running this twice leaves two
        // rows rather than four.
        User::updateOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Panel Manager',
                // The 'hashed' cast in User::casts() hashes this on save.
                'password' => 'password',
                'role' => 'manager',
            ],
        );

        User::updateOrCreate(
            ['email' => 'worker@example.com'],
            [
                'name' => 'Panel Worker',
                'password' => 'password',
                'role' => 'worker',
            ],
        );
    }
}
