<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FraudFlag;
use App\Models\PayoutRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CryptoPayoutAndAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function createVerifiedUserWithBalance(string $points = '10000.0000'): User
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'user',
        ]);

        $ledger = app(LedgerService::class);
        $ledger->creditOfferReward($user, $points, 'test_network', 'tx_fund_' . uniqid());

        return $user;
    }

    protected function createAdminUser(): User
    {
        /** @var User $admin */
        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'role' => 'admin',
        ]);

        return $admin;
    }

    public function test_user_cannot_withdraw_below_minimum_threshold(): void
    {
        $user = $this->createVerifiedUserWithBalance('10000.0000');

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_TRC20',
            'crypto_address' => 'TJ8q49uVhy6uF2E2p5v6nB54xTzP1Wk9Xy',
            'points_amount' => 4999, // Below 5000 min
        ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_withdraw_with_invalid_crypto_address(): void
    {
        $user = $this->createVerifiedUserWithBalance('10000.0000');

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_TRC20',
            'crypto_address' => '0xInvalidTRC20AddressThatStartsWrong',
            'points_amount' => 5000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid destination address format for USDT_TRC20.']);
    }

    public function test_user_cannot_withdraw_without_email_verification(): void
    {
        $user = User::factory()->unverified()->create();
        $ledger = app(LedgerService::class);
        $ledger->creditOfferReward($user, '10000.0000', 'test_network', 'tx_unv_' . uniqid());

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_TRC20',
            'crypto_address' => 'TJ8q49uVhy6uF2E2p5v6nB54xTzP1Wk9Xy',
            'points_amount' => 5000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Email address must be verified before submitting payout requests.']);
    }

    public function test_user_cannot_withdraw_more_than_available_balance(): void
    {
        $user = $this->createVerifiedUserWithBalance('6000.0000');

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'LTC',
            'crypto_address' => 'LM4b5K9U5hV6yF3E1p7v8nB94xTzP1Wk2X',
            'points_amount' => 8000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Insufficient available points balance.']);
    }

    public function test_valid_withdrawal_request_locks_balance_and_creates_pending_payout(): void
    {
        $user = $this->createVerifiedUserWithBalance('10000.0000');
        $address = 'TJ8q49uVhy6uF2E2p5v6nB54xTzP1Wk9Xy';

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_TRC20',
            'crypto_address' => $address,
            'points_amount' => 6000,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'data' => [
                'asset' => 'USDT_TRC20',
                'crypto_address' => $address,
                'points_amount' => '6000.0000',
                'usd_amount' => '6.0000',
                'status' => 'pending',
            ],
        ]);

        // Available balance must drop to 4000.0000
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();
        $this->assertEquals('4000.0000', $availableAccount->balance);

        // Locked balance must rise to 6000.0000
        $lockedAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_LOCKED)
            ->first();
        $this->assertEquals('6000.0000', $lockedAccount->balance);

        // Double-entry reconciliation
        $reconciliation = app(LedgerService::class)->reconcileSystem();
        $this->assertTrue($reconciliation['is_reconciled']);
    }

    public function test_reused_crypto_address_flags_accounts_for_fraud_review(): void
    {
        $user1 = $this->createVerifiedUserWithBalance('10000.0000');
        $user2 = $this->createVerifiedUserWithBalance('10000.0000');
        $sharedAddress = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';

        // User 1 requests payout
        $this->actingAs($user1)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_BEP20',
            'crypto_address' => $sharedAddress,
            'points_amount' => 5000,
        ])->assertStatus(201);

        // User 2 requests payout with the SAME address
        $this->actingAs($user2)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'USDT_BEP20',
            'crypto_address' => $sharedAddress,
            'points_amount' => 5000,
        ])->assertStatus(201);

        // Both accounts must have REUSED_CRYPTO_ADDRESS fraud flags
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user1->id,
            'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
            'is_resolved' => false,
        ]);

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user2->id,
            'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
            'is_resolved' => false,
        ]);
    }

    public function test_admin_can_approve_payout_and_settle_disbursement(): void
    {
        $user = $this->createVerifiedUserWithBalance('10000.0000');
        $admin = $this->createAdminUser();

        $payoutRes = $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'BTC',
            'crypto_address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'points_amount' => 7000,
        ]);
        $payoutRes->assertStatus(201);
        $payoutId = PayoutRequest::where('user_id', $user->id)->first()->id;

        // Admin approves payout
        $approveRes = $this->actingAs($admin)->postJson("/api/v1/admin/payouts/{$payoutId}/approve", [
            'notes' => 'Verified KYC documents and conversion logs.',
        ]);

        $approveRes->assertStatus(200);
        $approveRes->assertJson([
            'success' => true,
            'data' => [
                'status' => 'completed',
            ],
        ]);
        $this->assertNotNull($approveRes->json('data.tx_hash'));

        // Locked points settled to 0
        $lockedAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_LOCKED)
            ->first();
        $this->assertEquals('0.0000', $lockedAccount->balance);

        // System reconciliation remains balanced
        $this->assertTrue(app(LedgerService::class)->reconcileSystem()['is_reconciled']);
    }

    public function test_admin_can_reject_payout_and_refund_points(): void
    {
        $user = $this->createVerifiedUserWithBalance('10000.0000');
        $admin = $this->createAdminUser();

        $this->actingAs($user)->postJson('/api/v1/wallet/payouts', [
            'asset' => 'LTC',
            'crypto_address' => 'LM4b5K9U5hV6yF3E1p7v8nB94xTzP1Wk2X',
            'points_amount' => 5000,
        ])->assertStatus(201);

        $payout = PayoutRequest::where('user_id', $user->id)->first();

        // Admin rejects payout
        $rejectRes = $this->actingAs($admin)->postJson("/api/v1/admin/payouts/{$payout->id}/reject", [
            'reason' => 'Suspicious multi-IP rapid conversions.',
        ]);

        $rejectRes->assertStatus(200);
        $rejectRes->assertJson([
            'success' => true,
            'data' => [
                'status' => 'rejected',
                'admin_notes' => 'Suspicious multi-IP rapid conversions.',
            ],
        ]);

        // Locked points refunded back to available balance (4000 + 6000 = 10000.0000)
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();
        $this->assertEquals('10000.0000', $availableAccount->balance);

        $lockedAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_LOCKED)
            ->first();
        $this->assertEquals('0.0000', $lockedAccount->balance);
    }

    public function test_non_admin_cannot_access_admin_endpoints(): void
    {
        $regularUser = $this->createVerifiedUserWithBalance('10000.0000');

        $response = $this->actingAs($regularUser)->getJson('/api/v1/admin/stats');
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'ADMIN_UNAUTHORIZED',
        ]);
    }

    public function test_admin_can_manage_user_status_and_resolve_fraud_flags(): void
    {
        $targetUser = $this->createVerifiedUserWithBalance('10000.0000');
        $admin = $this->createAdminUser();

        // Admin updates user status to banned
        $statusRes = $this->actingAs($admin)->postJson("/api/v1/admin/users/{$targetUser->id}/status", [
            'status' => 'banned',
        ]);
        $statusRes->assertStatus(200);
        $this->assertEquals('banned', $targetUser->fresh()->status);

        // Create fraud flag
        $flag = FraudFlag::create([
            'user_id' => $targetUser->id,
            'flag_type' => FraudFlag::TYPE_VPN_DETECTED,
            'severity' => FraudFlag::SEVERITY_HIGH,
            'details' => ['test' => true],
            'is_resolved' => false,
        ]);

        // Admin resolves fraud flag
        $resolveRes = $this->actingAs($admin)->postJson("/api/v1/admin/fraud-flags/{$flag->id}/resolve");
        $resolveRes->assertStatus(200);
        $this->assertTrue($flag->fresh()->is_resolved);
    }
}
