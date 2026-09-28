<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;



test('a user can register and receives a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['message', 'user', 'token']);

    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
});

test('registering provisions all three currency wallets automatically', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    expect($user->wallets)->toHaveCount(3);
    expect($user->wallets->pluck('currency')->sort()->values()->all())
        ->toBe(['NGN', 'USD', 'USDT']);
    expect($user->wallets->every(fn ($w) => $w->balance === 0))->toBeTrue();
});

test('registration fails with a duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)->assertJsonStructure(['message', 'user', 'token']);
});

test('login fails with incorrect credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('email');
});

test('unauthenticated requests to protected routes are rejected', function () {
    $response = $this->getJson('/api/me');

    $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
});

test('an authenticated user can fetch their own profile', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/me');

    $response->assertStatus(200)
        ->assertJsonPath('user.email', $user->email);
});

test('a user can log out and their token is revoked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test_token')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/logout');

    $response->assertStatus(200);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});