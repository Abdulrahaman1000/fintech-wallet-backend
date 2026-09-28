<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

test('a user can fund their wallet and the balance updates correctly', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN',
        'amount' => 500.00,
        'idempotency_key' => 'test-key-1',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('wallet.balance', 50000);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'type' => 'funding',
        'amount' => 50000,
        'status' => 'success',
    ]);
});

test('funding rejects invalid currency', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'GBP',
        'amount' => 100,
        'idempotency_key' => 'test-key-2',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('currency');
});

test('funding rejects zero or negative amounts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', [
        'currency' => 'NGN',
        'amount' => -50,
        'idempotency_key' => 'test-key-3',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('amount');
});

test('repeating the same idempotency key does not double-credit the wallet', function () {
    $user = User::factory()->create();

    $payload = [
        'currency' => 'NGN',
        'amount' => 500.00,
        'idempotency_key' => 'duplicate-key-abc',
    ];

    $first = $this->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', $payload);
    $second = $this->actingAs($user, 'sanctum')->postJson('/api/wallets/fund', $payload);

    $first->assertStatus(201);
    $second->assertStatus(200)->assertJsonPath('message', 'Funding already processed.');

    $wallet = $user->fresh()->wallet('NGN');
    expect($wallet->balance)->toBe(50000); // not 100000

    $this->assertDatabaseCount('transactions', 1);
});

test('an unauthenticated user cannot fund a wallet', function () {
    $response = $this->postJson('/api/wallets/fund', [
        'currency' => 'NGN',
        'amount' => 500,
        'idempotency_key' => 'test-key-4',
    ]);

    $response->assertStatus(401);
});