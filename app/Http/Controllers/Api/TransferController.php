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
            $result = DB::transaction(function () use ($sender, $recipient, $currency, $amountMinorUnits) {

                $senderWallet = Wallet::where('user_id', $sender->id)->where('currency', $currency)->firstOrFail();
                $recipientWallet = Wallet::where('user_id', $recipient->id)->where('currency', $currency)->firstOrFail();

                $firstId = min($senderWallet->id, $recipientWallet->id);
                $secondId = max($senderWallet->id, $recipientWallet->id);

                $lockedFirst = Wallet::where('id', $firstId)->lockForUpdate()->first();
                $lockedSecond = Wallet::where('id', $secondId)->lockForUpdate()->first();

                $senderWallet = $senderWallet->id === $lockedFirst->id ? $lockedFirst : $lockedSecond;
                $recipientWallet = $recipientWallet->id === $lockedFirst->id ? $lockedFirst : $lockedSecond;

                if ($senderWallet->balance < $amountMinorUnits) {
                    return ['success' => false];
                }

                $senderWallet->balance -= $amountMinorUnits;
                $senderWallet->save();

                $recipientWallet->balance += $amountMinorUnits;
                $recipientWallet->save();

                return [
                    'success' => true,
                    'senderWalletId' => $senderWallet->id,
                    'recipientWalletId' => $recipientWallet->id,
                    'senderBalanceAfter' => $senderWallet->balance,
                    'recipientBalanceAfter' => $recipientWallet->balance,
                ];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                $existing = Transfer::where('reference', $reference)->first();
                return response()->json([
                    'message' => 'Transfer already processed.',
                    'transfer' => $existing,
                ]);
            }
            throw $e;
        }

        if (! $result['success']) {
            $senderWallet = Wallet::where('user_id', $sender->id)->where('currency', $currency)->first();
            $recipientWallet = Wallet::where('user_id', $recipient->id)->where('currency', $currency)->first();

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

            return response()->json([
                'message' => 'Transfer failed: insufficient balance.',
                'transfer' => $transfer,
            ], 422);
        }

        $transfer = Transfer::create([
            'reference' => $reference,
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'sender_wallet_id' => $result['senderWalletId'],
            'recipient_wallet_id' => $result['recipientWalletId'],
            'currency' => $currency,
            'amount' => $amountMinorUnits,
            'narration' => $narration,
            'status' => 'success',
        ]);

        Transaction::create([
            'reference' => 'TXN-'.Str::uuid(),
            'user_id' => $sender->id,
            'wallet_id' => $result['senderWalletId'],
            'type' => 'transfer_debit',
            'status' => 'success',
            'currency' => $currency,
            'amount' => $amountMinorUnits,
            'balance_after' => $result['senderBalanceAfter'],
            'counterparty_user_id' => $recipient->id,
            'narration' => $narration,
        ]);

        Transaction::create([
            'reference' => 'TXN-'.Str::uuid(),
            'user_id' => $recipient->id,
            'wallet_id' => $result['recipientWalletId'],
            'type' => 'transfer_credit',
            'status' => 'success',
            'currency' => $currency,
            'amount' => $amountMinorUnits,
            'balance_after' => $result['recipientBalanceAfter'],
            'counterparty_user_id' => $sender->id,
            'narration' => $narration,
        ]);

        return response()->json([
            'message' => 'Transfer successful.',
            'transfer' => $transfer,
        ], 201);
    }
}