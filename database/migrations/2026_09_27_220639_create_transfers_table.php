<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::create('transfers', function (Blueprint $table) {
        $table->id();
        $table->string('reference')->unique(); // idempotency key for the whole transfer
        $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('sender_wallet_id')->constrained('wallets');
        $table->foreignId('recipient_wallet_id')->constrained('wallets');
        $table->enum('currency', ['NGN', 'USD', 'USDT']);
        $table->bigInteger('amount'); // minor units
        $table->string('narration')->nullable();
        $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
        $table->string('failure_reason')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
