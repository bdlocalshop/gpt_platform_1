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
            $table->uuid('uuid')->unique();
            $table->string('type', 40)->index(); // offer_reward, payout_lock, payout_complete, payout_reject, reversal, signup_bonus, referral_bonus, adjustment
            $table->string('reference_type', 50)->nullable()->index(); // postback, payout_request, system
            $table->string('reference_id', 100)->nullable()->index();
            $table->string('status', 20)->default('completed')->index(); // pending, completed, failed, reversed
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
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
