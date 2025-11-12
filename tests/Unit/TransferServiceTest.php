<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure migrations run, users table will be extended by our migration
        $this->artisan('migrate');
    }

    public function test_successful_transfer_updates_balances_and_creates_transaction(): void
    {
        $sender = User::factory()->create(['balance' => '1000.0000']);
        $receiver = User::factory()->create(['balance' => '0.0000']);

        $service = new TransferService();
        $result = $service->transfer($sender, $receiver->id, '100.00');

        $this->assertSame('1.5000', (string) $result['transaction']->commission_fee);
        $this->assertSame('101.5000', (string) $result['transaction']->total_debited);
        $this->assertSame('898.5000', $result['balances']['sender']);
        $this->assertSame('100.0000', $result['balances']['receiver']);

        $this->assertDatabaseHas('transactions', [
            'id' => $result['transaction']->id,
            'sender_id' => $sender->id,
            'receiver_id' => $receiver->id,
            'amount' => '100.0000',
            'commission_fee' => '1.5000',
            'total_debited' => '101.5000',
            'status' => 'completed',
        ]);

        $sender->refresh();
        $receiver->refresh();
        $this->assertSame('898.5000', (string) $sender->balance);
        $this->assertSame('100.0000', (string) $receiver->balance);
    }

    public function test_transfer_fails_on_insufficient_funds_and_rolls_back(): void
    {
        $sender = User::factory()->create(['balance' => '50.0000']);
        $receiver = User::factory()->create(['balance' => '0.0000']);

        $service = new TransferService();

        try {
            $service->transfer($sender, $receiver->id, '100.00');
            $this->fail('Expected ValidationException for insufficient funds');
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame('50.0000', (string) $sender->fresh()->balance);
        $this->assertSame('0.0000', (string) $receiver->fresh()->balance);
    }
}
