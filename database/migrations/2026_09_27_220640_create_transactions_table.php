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
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->string('reference')->unique(); // per-ledger-row reference, always unique
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->foreignId('wallet_id')->constrained();
        $table->enum('type', ['funding', 'transfer_debit', 'transfer_credit']);
        $table->enum('status', ['pending', 'success', 'failed'])->default('pending');
        $table->enum('currency', ['NGN', 'USD', 'USDT']);
        $table->bigInteger('amount'); // minor units, always positive
        $table->bigInteger('balance_after'); // wallet balance snapshot right after this row
        $table->foreignId('counterparty_user_id')->nullable()->constrained('users');
        $table->string('narration')->nullable();
        $table->string('idempotency_key')->nullable()->unique(); // for funding requests specifically
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
