<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\TransferService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(public TransferService $transfers)
    {
    }

    /**
     * Return authenticated user's balance and paginated transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Transaction::query()
            ->where(function ($q) use ($user): void {
                $q->where('sender_id', $user->id)
                  ->orWhere('receiver_id', $user->id);
            })
            ->latest('id');

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate(
            perPage: (int) $request->integer('per_page', 20)
        )->appends($request->only(['per_page', 'page']));

        return response()->json([
            'balance' => (string) $user->balance,
            'transactions' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Perform a transfer.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $user = $request->user();
        $receiverId = (int) $request->integer('receiver_id');
        $amount = (string) $request->input('amount');

        $result = $this->transfers->transfer($user, $receiverId, $amount);

        return response()->json([
            'transaction' => [
                'id' => $result['transaction']->id,
                'sender_id' => $result['transaction']->sender_id,
                'receiver_id' => $result['transaction']->receiver_id,
                'amount' => (string) $result['transaction']->amount,
                'commission_fee' => (string) $result['transaction']->commission_fee,
                'total_debited' => (string) $result['transaction']->total_debited,
                'created_at' => $result['transaction']->created_at?->toISOString(),
            ],
            'balance' => $result['balances']['sender'],
        ], 201);
    }
}
