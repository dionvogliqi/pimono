<?php

namespace Tests\Feature;

use App\Events\TransferCompleted;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_transfer_dispatches_transfer_completed_event(): void
    {
        Event::fake([TransferCompleted::class]);

        $sender = User::factory()->create(['balance' => '500.0000']);
        $receiver = User::factory()->create(['balance' => '0.0000']);

        $this->actingAs($sender);

        $this->postJson('/api/transactions', [
            'receiver_id' => $receiver->id,
            'amount' => '100.00',
        ])->assertCreated();

        Event::assertDispatched(TransferCompleted::class, function (TransferCompleted $e) use ($sender, $receiver): bool {
            return $e->transaction->sender_id === $sender->id
                && $e->transaction->receiver_id === $receiver->id
                && (string) $e->senderBalance === '398.5000'
                && (string) $e->receiverBalance === '100.0000';
        });
    }
}
