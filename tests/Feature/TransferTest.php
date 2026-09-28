<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;



function fundWallet(User $user, string $currency, float $amount): void
{
    test()->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => $currency,
        'amount' => $amount,
        'idempotency_key' => (string) Str::uuid(),
    ]);
}

test('a user can transfer funds to another user successfully', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    fundWallet($sender, 'NGN', 1000.00);

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $recipient->email,
        'currency' => 'NGN',
        'amount' => 300.00,
        'reference' => (string) Str::uuid(),
        'narration' => 'Test transfer',
    ]);

    $response->assertStatus(201)->assertJsonPath('transfer.status', 'success');

    expect($sender->fresh()->wallet('NGN')->balance)->toBe(70000);
    expect($recipient->fresh()->wallet('NGN')->balance)->toBe(30000);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $sender->id,
        'type' => 'transfer_debit',
        'amount' => 30000,
    ]);
    $this->assertDatabaseHas('transactions', [
        'user_id' => $recipient->id,
        'type' => 'transfer_credit',
        'amount' => 30000,
    ]);
});

test('a transfer fails when the sender has insufficient balance', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();

    fundWallet($sender, 'NGN', 100.00);

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $recipient->email,
        'currency' => 'NGN',
        'amount' => 500.00,
        'reference' => (string) Str::uuid(),
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Transfer failed: insufficient balance.');

    // Balance must remain untouched — no partial debit.
    expect($sender->fresh()->wallet('NGN')->balance)->toBe(10000);
    expect($recipient->fresh()->wallet('NGN')->balance)->toBe(0);

    $this->assertDatabaseHas('transfers', ['status' => 'failed']);
});

test('a user cannot transfer funds to themselves', function () {
    $sender = User::factory()->create();
    fundWallet($sender, 'NGN', 1000.00);

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => $sender->email,
        'currency' => 'NGN',
        'amount' => 100.00,
        'reference' => (string) Str::uuid(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('recipient_email');
});

test('a transfer to a non-existent recipient fails validation', function () {
    $sender = User::factory()->create();
    fundWallet($sender, 'NGN', 1000.00);

    $response = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', [
        'recipient_email' => 'nobody@example.com',
        'currency' => 'NGN',
        'amount' => 100.00,
        'reference' => (string) Str::uuid(),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('recipient_email');
});

test('reusing the same transfer reference does not process the transfer twice', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    fundWallet($sender, 'NGN', 1000.00);

    $reference = (string) Str::uuid();
    $payload = [
        'recipient_email' => $recipient->email,
        'currency' => 'NGN',
        'amount' => 200.00,
        'reference' => $reference,
    ];

    $first = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', $payload);
    $first->assertStatus(201);

    // Second submission with the SAME reference must fail validation (unique rule)
    // rather than silently executing again.
    $second = $this->actingAs($sender, 'sanctum')->postJson('/api/transfers', $payload);
    $second->assertStatus(422)->assertJsonValidationErrors('reference');

    expect($sender->fresh()->wallet('NGN')->balance)->toBe(80000); // debited only once
});