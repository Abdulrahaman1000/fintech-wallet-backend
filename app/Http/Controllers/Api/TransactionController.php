<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * List the authenticated user's own transactions, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::where('user_id', $request->user()->id)
            ->with('counterparty:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Show a single transaction — only if it belongs to the authenticated user.
     */
    public function show(Request $request, Transaction $transaction): JsonResponse
    {
        if ($transaction->user_id !== $request->user()->id) {
            // Return 404, not 403 — never reveal that a transaction ID exists at all.
            abort(404);
        }

        $transaction->load('counterparty:id,name,email', 'wallet:id,currency');

        return response()->json(['transaction' => $transaction]);
    }
}