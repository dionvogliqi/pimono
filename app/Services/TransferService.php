<?php

namespace App\Services;

use App\Events\TransferCompleted;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService
{
    public const COMMISSION_RATE = '0.015'; // 1.5%

    public const SCALE = 4; // money scale

    public function __construct()
    {
        bcscale(self::SCALE);
    }

    /**
     * Perform an atomic transfer between two users.
     *
     * @param  string  $amount  Decimal string, up to 4 fractional digits
     * @return array{transaction: Transaction, balances: array{sender: string, receiver: string}}
     *
     * @throws ValidationException
     */
    public function transfer(User $sender, int $receiverId, string $amount): array
    {
        $amount = $this->normalizeMoney($amount);

        if ($sender->id === $receiverId) {
            throw ValidationException::withMessages([
                'receiver_id' => ['The receiver must be a different user.'],
            ]);
        }

        if ($this->ltEq($amount, '0')) {
            throw ValidationException::withMessages([
                'amount' => ['The amount must be greater than 0.'],
            ]);
        }

        $maxAttempts = 5;
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            try {
                $result = DB::transaction(function () use ($sender, $receiverId, $amount) {
                    // Determine consistent lock order
                    $firstId = min($sender->id, $receiverId);
                    $secondId = max($sender->id, $receiverId);

                    // Lock first user row
                    /** @var User|null $first */
                    $first = User::query()->whereKey($firstId)->lockForUpdate()->first();
                    /** @var User|null $second */
                    $second = User::query()->whereKey($secondId)->lockForUpdate()->first();

                    if ($first === null || $second === null) {
                        throw ValidationException::withMessages([
                            'receiver_id' => ['The selected receiver is invalid.'],
                        ]);
                    }

                    // Map to sender/receiver after lock
                    $lockedSender = $sender->id === $first->id ? $first : $second;
                    $lockedReceiver = $sender->id === $first->id ? $second : $first;

                    // Compute commission and totals
                    $commission = $this->roundMoney(bcmul($amount, self::COMMISSION_RATE));
                    $totalDebited = $this->roundMoney(bcadd($amount, $commission));

                    // Check sufficient funds
                    if ($this->lt($lockedSender->balance, $totalDebited)) {
                        throw ValidationException::withMessages([
                            'amount' => ['Insufficient funds.'],
                        ])->status(403);
                    }

                    // Update balances
                    $newSenderBalance = $this->roundMoney(bcsub((string) $lockedSender->balance, $totalDebited));
                    $newReceiverBalance = $this->roundMoney(bcadd((string) $lockedReceiver->balance, $amount));

                    $lockedSender->forceFill([
                        'balance' => $newSenderBalance,
                        'balance_version' => ((int) $lockedSender->balance_version) + 1,
                    ])->save();

                    $lockedReceiver->forceFill([
                        'balance' => $newReceiverBalance,
                        'balance_version' => ((int) $lockedReceiver->balance_version) + 1,
                    ])->save();

                    // Create transaction record
                    $tx = new Transaction([
                        'sender_id' => $lockedSender->id,
                        'receiver_id' => $lockedReceiver->id,
                        'amount' => $amount,
                        'commission_fee' => $commission,
                        'total_debited' => $totalDebited,
                        'status' => 'completed',
                        'meta' => null,
                    ]);

                    $tx->save();

                    // Broadcast event (synchronous). In production, use queue for ShouldBroadcast.
                    event(new TransferCompleted($tx, $newSenderBalance, $newReceiverBalance));

                    return [
                        'transaction' => $tx,
                        'balances' => [
                            'sender' => $newSenderBalance,
                            'receiver' => $newReceiverBalance,
                        ],
                    ];
                }, attempts: 1);

                return $result;
            } catch (QueryException $e) {
                $lastException = $e;
                if ($this->isDeadlockOrLockTimeout($e)) {
                    $attempt++;
                    // exponential backoff 5ms, 10ms, 20ms, 40ms...
                    usleep((int) (5000 * (2 ** ($attempt - 1))));

                    continue;
                }

                throw $e;
            }
        }

        // If we exhausted attempts
        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('Transfer failed after retries.');
    }

    protected function isDeadlockOrLockTimeout(QueryException $e): bool
    {
        $sqlState = $e->getCode(); // sometimes string like '40001'
        $message = $e->getMessage();

        return in_array($sqlState, ['40001', '1213', '1205'], true)
            || str_contains($message, 'Deadlock')
            || str_contains($message, 'deadlock')
            || str_contains($message, 'Lock wait timeout');
    }

    protected function normalizeMoney(string $value): string
    {
        // Trim and normalize to 4 decimals without rounding issues
        if ($value === '') {
            return '0.0000';
        }
        if (! str_contains($value, '.')) {
            return $value.'.0000';
        }
        $parts = explode('.', $value, 2);
        $frac = substr($parts[1], 0, self::SCALE);

        return $parts[0].'.'.str_pad($frac, self::SCALE, '0');
    }

    protected function roundMoney(string $value, int $scale = self::SCALE): string
    {
        // HALF UP rounding using an extra digit
        $sign = str_starts_with($value, '-') ? -1 : 1;
        $abs = ltrim($value, '+-');
        if (! str_contains($abs, '.')) {
            $abs .= '.'.str_repeat('0', $scale + 1);
        }
        [$int, $frac] = explode('.', $abs, 2);
        $frac = str_pad($frac, $scale + 1, '0');
        $carryDigit = (int) substr($frac, $scale, 1);
        $kept = substr($frac, 0, $scale);

        if ($carryDigit >= 5) {
            // add 1 to the kept fraction
            $increment = '0.'.str_pad('1', $scale, '0', STR_PAD_LEFT);
            $rounded = bcadd($int.'.'.$kept, $increment, $scale);
        } else {
            $rounded = $int.'.'.$kept;
        }

        return $sign === -1 && $rounded !== '0.'.str_repeat('0', $scale)
            ? '-'.ltrim($rounded, '+-')
            : ltrim($rounded, '+-');
    }

    protected function lt(string $a, string $b): bool
    {
        return bccomp($a, $b, self::SCALE) === -1;
    }

    protected function ltEq(string $a, string $b): bool
    {
        $cmp = bccomp($a, $b, self::SCALE);

        return $cmp === -1 || $cmp === 0;
    }
}
