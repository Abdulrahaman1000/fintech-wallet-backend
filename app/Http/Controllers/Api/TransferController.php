<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransferRequest;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferController extends Controller
{
    public function store(TransferRequest $request): JsonResponse
    {
        $sender = $request->user();
        $recipient = User::where('email', $request->validated('recipient_email'))->firstOrFail();
        $currency = $request->validated('currency');
        $amountMinorUnits = (int) round($request->validated('amount') * 100);
        $reference = $request->validated('reference');
        $narration = $request->validated('narration');

        try {
            $result = DB::transaction(function () use ($sender, $recipient, $currency, $amountMinorUnits, $reference, $narration) {

                $senderWallet = Wallet::where('user_id', $sender->id)->where('currency', $currency)->firstOrFail();
                $recipientWallet = Wallet::where('user_id', $recipient->id)->where('currency', $currency)->firstOrFail();

                // Lock both wallets in a CONSISTENT order (lowest id first) to prevent deadlocks
                // between simultaneous opposite-direction transfers.
                $firstId = min($senderWallet->id, $recipientWallet->id);
                $secondId = max($senderWallet->id, $recipientWallet->id);

                $lockedFirst = Wallet::where('id', $firstId)->lockForUpdate()->first();
                $lockedSecond = Wallet::where('id', $secondId)->lockForUpdate()->first();

                $senderWallet = $senderWallet->id === $lockedFirst->id ? $lockedFirst : $lockedSecond;
                $recipientWallet = $recipientWallet->id === $lockedFirst->id ? $lockedFirst : $lockedSecond;

                // Now both rows are locked and we have the freshest balance possible.
                if ($senderWallet->balance < $amountMinorUnits) {
                    // Record the failed attempt for audit purposes, then abort.
                    $transfer = Transfer::create([
                        'reference' => $reference,
                        'sender_id' => $sender->id,
                        'recipient_id' => $recipient->id,
                        'sender_wallet_id' => $senderWallet->id,
                        'recipient_wallet_id' => $recipientWallet->id,
                        'currency' => $currency,
                        'amount' => $amountMinorUnits,
                        'narration' => $narration,
                        'status' => 'failed',
                        'failure_reason' => 'Insufficient balance.',
                    ]);

                    throw new \App\Exceptions\InsufficientBalanceException($transfer);
                }

                // Debit sender
                $senderWallet->balance -= $amountMinorUnits;
                $senderWallet->save();

                // Credit recipient
                $recipientWallet->balance += $amountMinorUnits;
                $recipientWallet->save();

                $transfer = Transfer::create([
                    'reference' => $reference,
                    'sender_id' => $sender->id,
                    'recipient_id' => $recipient->id,
                    'sender_wallet_id' => $senderWallet->id,
                    'recipient_wallet_id' => $recipientWallet->id,
                    'currency' => $currency,
                    'amount' => $amountMinorUnits,
                    'narration' => $narration,
                    'status' => 'success',
                ]);

                $debitTxn = Transaction::create([
                    'reference' => 'TXN-'.Str::uuid(),
                    'user_id' => $sender->id,
                    'wallet_id' => $senderWallet->id,
                    'type' => 'transfer_debit',
                    'status' => 'success',
                    'currency' => $currency,
                    'amount' => $amountMinorUnits,
                    'balance_after' => $senderWallet->balance,
                    'counterparty_user_id' => $recipient->id,
                    'narration' => $narration,
                ]);

                $creditTxn = Transaction::create([
                    'reference' => 'TXN-'.Str::uuid(),
                    'user_id' => $recipient->id,
                    'wallet_id' => $recipientWallet->id,
                    'type' => 'transfer_credit',
                    'status' => 'success',
                    'currency' => $currency,
                    'amount' => $amountMinorUnits,
                    'balance_after' => $recipientWallet->balance,
                    'counterparty_user_id' => $sender->id,
                    'narration' => $narration,
                ]);

                return compact('transfer', 'debitTxn', 'creditTxn');
            });
        } catch (\App\Exceptions\InsufficientBalanceException $e) {
            return response()->json([
                'message' => 'Transfer failed: insufficient balance.',
                'transfer' => $e->transfer,
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                // Reference collision: this exact transfer was already submitted (double-click / retry).
                $existing = Transfer::where('reference', $reference)->first();
                return response()->json([
                    'message' => 'Transfer already processed.',
                    'transfer' => $existing,
                ]);
            }
            throw $e;
        }

        return response()->json([
            'message' => 'Transfer successful.',
            'transfer' => $result['transfer'],
        ], 201);
    }
}