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
        Schema::create('payout_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('asset', 30); // USDT_TRC20, USDT_BEP20, LTC, BTC
            $table->string('crypto_address', 120)->index();
            $table->decimal('points_amount', 16, 4);
            $table->decimal('usd_amount', 16, 4);
            $table->decimal('crypto_amount', 24, 8)->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending, processing, completed, rejected, cancelled
            $table->string('ip_address', 45);
            $table->string('device_fingerprint', 128)->nullable()->index();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('tx_hash', 128)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_requests');
    }
};
