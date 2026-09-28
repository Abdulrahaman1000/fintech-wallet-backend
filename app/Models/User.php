<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * All wallets belonging to this user (one per currency).
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * All ledger transactions belonging to this user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Transfers this user has sent.
     */
    public function sentTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'sender_id');
    }

    /**
     * Transfers this user has received.
     */
    public function receivedTransfers(): HasMany
    {
        return $this->hasMany(Transfer::class, 'recipient_id');
    }

    /**
     * Get a specific wallet belonging to this user by currency.
     * Throws if it doesn't exist — should never happen since booted() creates all three.
     */
    public function wallet(string $currency): Wallet
    {
        return $this->wallets()->where('currency', $currency)->firstOrFail();
    }

    /**
     * Automatically provision NGN/USD/USDT wallets for every new user.
     */
    protected static function booted(): void
    {
        static::created(function (User $user) {
            foreach (['NGN', 'USD', 'USDT'] as $currency) {
                $user->wallets()->create([
                    'currency' => $currency,
                    'balance' => 0,
                ]);
            }
        });
    }
}