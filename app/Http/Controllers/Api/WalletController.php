<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FundWalletRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    /**
     * List the authenticated user's wallets and balances.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'wallets' => $request->user()->wallets,
        ]);
    }

    /**
     * Simulate funding a wallet. Idempotent: replaying the same
     * idempotency_key returns the original transaction instead of
     * crediting the wallet again.
     */
    public function fund(FundWalletRequest $request): JsonResponse
    {
        $user = $request->user();
        $currency = $request->validated('currency');
        $amountMinorUnits = (int) round($request->validated('amount') * 100);
        $idempotencyKey = $request->validated('idempotency_key');

        // Idempotency check BEFORE opening a transaction — cheap short-circuit.
        $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return response()->json([
                'message' => 'Funding already processed.',
                'transaction' => $existing,
                'wallet' => $existing->wallet,
            ]);
        }

        try {
            $result = DB::transaction(function () use ($user, $currency, $amountMinorUnits, $idempotencyKey) {
                // Lock the wallet row so no concurrent request can read a stale balance.
                $wallet = Wallet::where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->lockForUpdate()
                    ->firstOrFail();

                $wallet->balance += $amountMinorUnits;
                $wallet->save();

                $transaction = Transaction::create([
                    'reference' => 'FUND-'.Str::uuid(),
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'type' => 'funding',
                    'status' => 'success',
                    'currency' => $currency,
                    'amount' => $amountMinorUnits,
                    'balance_after' => $wallet->balance,
                    'idempotency_key' => $idempotencyKey,
                ]);

                return ['wallet' => $wallet, 'transaction' => $transaction];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint race: two identical requests hit simultaneously.
            if ($e->getCode() === '23000') {
                $existing = Transaction::where('idempotency_key', $idempotencyKey)->first();
                return response()->json([
                    'message' => 'Funding already processed.',
                    'transaction' => $existing,
                    'wallet' => $existing?->wallet,
                ]);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Wallet funded successfully.',
            'wallet' => $result['wallet'],
            'transaction' => $result['transaction'],
        ], 201);
    }
}