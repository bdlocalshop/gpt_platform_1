<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Postback;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ledger\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetentionAndReferralTest extends TestCase
{
    use RefreshDatabase;

    public function test_signup_bonus_awarded_upon_email_verification(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();

        // Initially no balance
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();
        $this->assertTrue($availableAccount === null || bccomp($availableAccount->balance, '0.0000', 4) === 0);

        // Verify email
        $hash = sha1($user->getEmailForVerification());
        $response = $this->getJson("/api/v1/auth/email/verify/{$user->id}/{$hash}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'verified' => true,
                'bonus_credited' => true,
                'bonus_points' => '100.0000',
            ],
        ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Assert 100 points credited via double-entry transaction
        $this->assertDatabaseHas('transactions', [
            'type' => Transaction::TYPE_SIGNUP_BONUS,
            'reference_id' => (string) $user->id,
        ]);

        $this->assertDatabaseHas('ledger_entries', [
            'amount' => '100.0000',
            'direction' => \App\Models\LedgerEntry::DIRECTION_CREDIT,
        ]);

        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertNotNull($availableAccount);
        $this->assertEquals('100.0000', $availableAccount->balance);
    }

    public function test_signup_bonus_is_strictly_awarded_only_once(): void
    {
        /** @var User $user */
        $user = User::factory()->unverified()->create();
        $hash = sha1($user->getEmailForVerification());

        // First verification -> credits 100.0000
        $this->getJson("/api/v1/auth/email/verify/{$user->id}/{$hash}")->assertStatus(200);

        // Second verification -> already verified, no double credit
        $res2 = $this->getJson("/api/v1/auth/email/verify/{$user->id}/{$hash}");
        $res2->assertStatus(200);
        $res2->assertJson([
            'success' => true,
            'data' => [
                'already_verified' => true,
            ],
        ]);

        $this->assertEquals(
            1,
            Transaction::where('type', Transaction::TYPE_SIGNUP_BONUS)
                ->where('reference_id', (string) $user->id)
                ->count()
        );

        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertEquals('100.0000', $availableAccount->balance);
    }

    public function test_referral_attribution_on_registration(): void
    {
        $referrer = User::factory()->create([
            'referral_code' => 'TOPREF88',
        ]);

        $response = $this->withHeaders([
            'X-Device-Fingerprint' => 'ref_test_fp_' . uniqid(),
        ])->postJson('/api/v1/auth/register', [
            'name' => 'Referred Invitee',
            'username' => 'invitee_' . uniqid(),
            'email' => 'invitee_' . uniqid() . '@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'referral_code' => 'TOPREF88',
        ]);

        $response->assertStatus(201);
        $inviteeId = $response->json('data.user.id');

        $invitee = User::find($inviteeId);
        $this->assertNotNull($invitee);
        $this->assertEquals($referrer->id, $invitee->referred_by_id);
    }

    public function test_referrals_dashboard_endpoint(): void
    {
        $referrer = User::factory()->create([
            'referral_code' => 'TESTDASH',
        ]);

        $referee1 = User::factory()->create(['referred_by_id' => $referrer->id]);
        $referee2 = User::factory()->create(['referred_by_id' => $referrer->id]);

        $ledgerService = app(LedgerService::class);

        // Referee 1 has 400 points (< 1000 threshold, pending)
        Postback::create([
            'provider' => 'bitlabs',
            'external_transaction_id' => 'tx_dash_1',
            'user_id' => $referee1->id,
            'amount' => '400.0000',
            'status' => Postback::STATUS_CREDITED,
            'is_reversal' => false,
            'ip_address' => '127.0.0.1',
            'raw_payload' => [],
        ]);

        // Referee 2 has 1200 points (qualified) and referral bonus was awarded
        Postback::create([
            'provider' => 'cpx',
            'external_transaction_id' => 'tx_dash_2',
            'user_id' => $referee2->id,
            'amount' => '1200.0000',
            'status' => Postback::STATUS_CREDITED,
            'is_reversal' => false,
            'ip_address' => '127.0.0.1',
            'raw_payload' => [],
        ]);

        $ledgerService->creditReferralBonus(
            referrer: $referrer,
            referred: $referee2,
            amount: '500.0000'
        );

        $response = $this->actingAs($referrer)->getJson('/api/v1/referrals');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'referral_code' => 'TESTDASH',
                'statistics' => [
                    'total_referrals' => 2,
                    'qualified_referrals' => 1,
                    'pending_referrals' => 1,
                    'total_earned_points' => '500.0000',
                    'total_earned_usd' => '0.50',
                ],
            ],
        ]);

        $refereesData = $response->json('data.referees');
        $this->assertCount(2, $refereesData);
    }

    public function test_wallet_transactions_paginated_history(): void
    {
        $user = User::factory()->create();
        $ledgerService = app(LedgerService::class);

        // Award signup bonus (100) and offer reward (2500)
        $ledgerService->creditSignupBonus($user, '100.0000');
        $ledgerService->creditOfferReward($user, '2500.0000', 'bitlabs', 'tx_hist_test');

        $response = $this->actingAs($user)->getJson('/api/v1/wallet/transactions');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'transactions' => [
                    '*' => [
                        'id',
                        'direction',
                        'amount',
                        'amount_usd',
                        'balance_after',
                        'transaction' => ['uuid', 'type', 'description'],
                    ],
                ],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ],
        ]);

        // Filter by type
        $filterRes = $this->actingAs($user)->getJson('/api/v1/wallet/transactions?type=' . Transaction::TYPE_SIGNUP_BONUS);
        $filterRes->assertStatus(200);
        $this->assertCount(1, $filterRes->json('data.transactions'));
        $this->assertEquals(Transaction::TYPE_SIGNUP_BONUS, $filterRes->json('data.transactions.0.transaction.type'));
    }
}
