<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'amount',
        'commission_fee',
        'total_debited',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'commission_fee' => 'decimal:4',
            'total_debited' => 'decimal:4',
            'meta' => 'array',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
