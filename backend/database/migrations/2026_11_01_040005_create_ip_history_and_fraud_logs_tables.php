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
        Schema::create('user_ip_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->index();
            $table->string('device_fingerprint', 128)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->string('user_agent_hash', 64)->nullable()->index();
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);
            $table->integer('risk_score')->default(0)->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('city', 100)->nullable();
            $table->string('isp', 150)->nullable();
            $table->string('asn', 50)->nullable();
            $table->string('request_path', 255)->nullable();
            $table->string('action', 50)->default('request')->index(); // registration, login, offer_access, withdrawal
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('flag_type', 50)->index(); // multi_account_fingerprint, multi_account_ip, vpn_detected, reused_crypto_address
            $table->string('severity', 20)->default('medium')->index(); // low, medium, high, critical
            $table->json('details')->nullable();
            $table->boolean('is_resolved')->default(false)->index();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_resolved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('user_ip_logs');
    }
};
