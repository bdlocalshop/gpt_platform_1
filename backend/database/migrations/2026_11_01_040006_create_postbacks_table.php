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
        Schema::create('postbacks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index(); // bitlabs, cpx, offerwall_gg, offerwall_com
            $table->string('external_transaction_id', 100)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->decimal('amount', 16, 4);
            $table->string('status', 20)->default('credited')->index(); // credited, reversed, duplicate, rejected
            $table->boolean('is_reversal')->default(false)->index();
            $table->string('signature', 255)->nullable();
            $table->string('ip_address', 45);
            $table->json('raw_payload');
            $table->timestamps();

            // Idempotency rule: absolute uniqueness per provider transaction ID
            $table->unique(['provider', 'external_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postbacks');
    }
};
