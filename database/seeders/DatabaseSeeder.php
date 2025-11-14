<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed demo users and transactions for a better out-of-the-box experience
        $this->call([
            UserSeeder::class,
            TransactionSeeder::class,
        ]);
    }
}
