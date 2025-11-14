<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed a small set of demo users with predictable credentials and balances.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Alice Demo',
                'email' => 'alice@example.com',
                'password' => 'password',
                'balance' => '1500.0000',
            ],
            [
                'name' => 'Bob Demo',
                'email' => 'bob@example.com',
                'password' => 'password',
                'balance' => '300.0000',
            ],
            [
                'name' => 'Charlie Demo',
                'email' => 'charlie@example.com',
                'password' => 'password',
                'balance' => '100.0000',
            ],
            [
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => 'password',
                'balance' => '1000.0000',
            ],
        ];

        foreach ($users as $data) {
            /** @var User $user */
            $user = User::query()->firstOrNew(['email' => $data['email']]);

            $user->fill([
                'name' => $data['name'],
                'password' => $data['password'], // hashed via casts()
            ]);

            // Only set the balance if creating or if not set yet
            if (! $user->exists || $user->getOriginal('balance') === null) {
                $user->balance = $data['balance'];
            }

            // Ensure email is verified for convenience
            if (! $user->email_verified_at) {
                $user->email_verified_at = now();
            }

            $user->save();
        }
    }
}
