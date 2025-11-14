<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

/**
 * Seed a realistic set of transactions between demo users.
 */
class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Avoid duplicating if transactions already present
        if (Transaction::query()->exists()) {
            return;
        }

        // Ensure demo users exist (idempotent)
        $this->call(UserSeeder::class);

        /** @var User $alice */
        $alice = User::query()->where('email', 'alice@example.com')->firstOrFail();
        /** @var User $bob */
        $bob = User::query()->where('email', 'bob@example.com')->firstOrFail();
        /** @var User $charlie */
        $charlie = User::query()->where('email', 'charlie@example.com')->firstOrFail();
        /** @var User $demo */
        $demo = User::query()->where('email', 'demo@example.com')->firstOrFail();

        $service = app(TransferService::class);

        // Don't broadcast during seeding
        Event::fake();

        $transfers = [
            // sender, receiver, amount
            [$demo, $alice, '100.0000'],
            [$alice, $bob, '50.0000'],
            [$bob, $charlie, '25.0000'],
            [$alice, $charlie, '10.0000'],
            [$demo, $bob, '75.0000'],
            [$charlie, $demo, '5.0000'],
        ];

        foreach ($transfers as [$sender, $receiver, $amount]) {
            // Use the domain service to ensure balances and fees are correct
            $service->transfer($sender, $receiver->id, $amount);
        }

        // Create a few extra random users and transactions for UI richness
        $extras = User::factory(5)->create(['balance' => '200.0000']);
        foreach ($extras as $extra) {
            // Each extra user sends a small transfer to Demo User
            $service->transfer($extra, $demo->id, '10.0000');
        }
    }
}
