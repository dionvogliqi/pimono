<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TransferCompleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Transaction $transaction,
        public string $senderBalance,
        public string $receiverBalance,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('private-user.'.$this->transaction->sender_id),
            new PrivateChannel('private-user.'.$this->transaction->receiver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TransferCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'transaction' => [
                'id' => $this->transaction->id,
                'sender_id' => $this->transaction->sender_id,
                'receiver_id' => $this->transaction->receiver_id,
                'amount' => (string) $this->transaction->amount,
                'commission_fee' => (string) $this->transaction->commission_fee,
                'total_debited' => (string) $this->transaction->total_debited,
                'status' => $this->transaction->status,
                'created_at' => $this->transaction->created_at?->toISOString(),
            ],
            'balances' => [
                'sender' => $this->senderBalance,
                'receiver' => $this->receiverBalance,
            ],
        ];
    }
}
