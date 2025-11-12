<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TransactionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_index_returns_balance_and_transactions(): void
    {
        $user = User::factory()->create(['balance' => '123.4567']);
        $other = User::factory()->create();
        Transaction::factory()->for($user, 'sender')->for($other, 'receiver')->create([
            'amount' => '10.0000', 'commission_fee' => '0.1500', 'total_debited' => '10.1500', 'status' => 'completed',
        ]);
        Transaction::factory()->for($other, 'sender')->for($user, 'receiver')->create([
            'amount' => '5.0000', 'commission_fee' => '0.0750', 'total_debited' => '5.0750', 'status' => 'completed',
        ]);

        $this->actingAs($user);

        $res = $this->getJson('/api/transactions');
        $res->assertOk();
        $res->assertJsonStructure([
            'balance',
            'transactions',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
        $this->assertSame('123.4567', $res->json('balance'));
        $this->assertCount(2, $res->json('transactions'));
    }

    public function test_store_performs_transfer_and_returns_201(): void
    {
        $sender = User::factory()->create(['balance' => '500.0000']);
        $receiver = User::factory()->create(['balance' => '0.0000']);

        $this->actingAs($sender);

        $res = $this->postJson('/api/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => '100.00',
        ]);

        $res->assertCreated();
        $res->assertJsonStructure([
            'transaction' => ['id', 'sender_id', 'receiver_id', 'amount', 'commission_fee', 'total_debited', 'created_at'],
            'balance',
        ]);
        $this->assertSame('898.5000', $res->json('balance'));

        $sender->refresh();
        $receiver->refresh();
        $this->assertSame('898.5000', (string) $sender->balance);
        $this->assertSame('100.0000', (string) $receiver->balance);
    }

    public function test_store_validates_and_blocks_self_transfer(): void
    {
        $user = User::factory()->create(['balance' => '100.0000']);
        $this->actingAs($user);

        $res = $this->postJson('/api/transactions', [
            'receiver_id' => $user->id,
            'amount' => '10.00',
        ]);

        $res->assertStatus(422);
    }
}
