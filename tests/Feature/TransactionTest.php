<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;



test('a user can only see their own transactions', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    test()->actingAs($sender, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN', 'amount' => 500.00, 'idempotency_key' => (string) Str::uuid(),
    ]);

    test()->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $recipient->email,
        'currency' => 'NGN',
        'amount' => 100.00,
        'reference' => (string) Str::uuid(),
    ]);

    $senderResponse = $this->actingAs($sender, 'sanctum')->getJson('/api/transactions');
    $recipientResponse = $this->actingAs($recipient, 'sanctum')->getJson('/api/transactions');

    $senderResponse->assertStatus(200);
    expect(count($senderResponse->json('data')))->toBe(2); // funding + debit

    $recipientResponse->assertStatus(200);
    expect(count($recipientResponse->json('data')))->toBe(1); // credit only
});

test('a user cannot view another users transaction detail (returns 404)', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    test()->actingAs($sender, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN', 'amount' => 500.00, 'idempotency_key' => (string) Str::uuid(),
    ]);

    $fundingTxnId = $sender->transactions()->first()->id;

    $response = $this->actingAs($recipient, 'sanctum')->getJson("/api/transactions/{$fundingTxnId}");

    $response->assertStatus(404);
});

test('a user can view their own transaction detail', function () {
    $user = User::factory()->create();

    test()->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN', 'amount' => 500.00, 'idempotency_key' => (string) Str::uuid(),
    ]);

    $txnId = $user->transactions()->first()->id;

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/transactions/{$txnId}");

    $response->assertStatus(200)->assertJsonPath('transaction.id', $txnId);
});