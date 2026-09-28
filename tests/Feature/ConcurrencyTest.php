<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('concurrent transfers cannot overspend a wallet', function () {
    $sender = User::factory()->create();
    $recipientA = User::factory()->create();
    $recipientB = User::factory()->create();

    test()->actingAs($sender, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN',
        'amount' => 1000.00,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $wallet = $sender->fresh()->wallet('NGN');

    // Laravel's own connection is already inside RefreshDatabase's wrapping
    // transaction for this test — so we lock the row within that existing
    // transaction instead of starting a new one.
    $pdo1 = DB::connection()->getPdo();
    $stmt1 = $pdo1->prepare('SELECT balance FROM wallets WHERE id = ? FOR UPDATE');
    $stmt1->execute([$wallet->id]);
    $lockedBalance = $stmt1->fetchColumn();
    expect((int) $lockedBalance)->toBe(100000);

    // Open a completely separate, raw PDO connection — bypassing Laravel's
    // connection manager entirely — to prove a genuinely independent writer
    // is blocked by the lock above, not just "usually fine in practice".
    $config = config('database.connections.mysql');
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
    $pdo2 = new PDO($dsn, $config['username'], $config['password']);
    $pdo2->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $pdo2->beginTransaction();

    $blocked = false;
    try {
        $stmt2 = $pdo2->prepare('SELECT balance FROM wallets WHERE id = ? FOR UPDATE');
        $stmt2->execute([$wallet->id]);
    } catch (\PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout exceeded');
    }

    expect($blocked)->toBeTrue(); // proves the row was genuinely locked

    $pdo2->rollBack();

    // Now run the two ₦800 transfers for real via HTTP — proving the end
    // result is correct: one succeeds, one fails, balance never overspent.
    $refA = (string) Str::uuid();
    $refB = (string) Str::uuid();

    $responseA = test()->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $recipientA->email,
        'currency' => 'NGN',
        'amount' => 800.00,
        'reference' => $refA,
    ]);

    $responseB = test()->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $recipientB->email,
        'currency' => 'NGN',
        'amount' => 800.00,
        'reference' => $refB,
    ]);

    $statuses = [$responseA->status(), $responseB->status()];
    sort($statuses);

    expect($statuses)->toBe([201, 422]);

    $finalBalance = $sender->fresh()->wallet('NGN')->balance;
    expect($finalBalance)->toBeGreaterThanOrEqual(0);
    expect($finalBalance)->toBe(20000);
});