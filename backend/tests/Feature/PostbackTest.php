<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\FraudFlag;
use App\Models\Postback;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.offerwalls.bitlabs.secret' => 'test_bitlabs_secret',
            'services.offerwalls.bitlabs.ip_whitelist' => '127.0.0.1,::1,54.216.1.1',
            'services.offerwalls.cpx.secure_hash' => 'test_cpx_hash',
            'services.offerwalls.cpx.ip_whitelist' => '127.0.0.1,::1,168.119.1.1',
            'services.offerwalls.offerwall_gg.secret' => 'test_owgg_secret',
            'services.offerwalls.offerwall_gg.ip_whitelist' => '127.0.0.1,::1,104.21.1.1',
            'services.offerwalls.offerwall_com.secret' => 'test_owcom_secret',
            'services.offerwalls.offerwall_com.ip_whitelist' => '127.0.0.1,::1,198.51.100.1',
        ]);
    }

    public function test_bitlabs_valid_credit_postback_creates_ledger_entry_and_credits_balance(): void
    {
        $user = User::factory()->create();
        $txId = 'tx_bl_' . uniqid();
        $amount = '1500.0000';
        $secret = 'test_bitlabs_secret';
        $sig = hash_hmac('sha256', "{$user->id}:{$txId}:{$amount}", $secret);

        $response = $this->withHeaders([
            'X-Simulate-Ip' => '127.0.0.1',
        ])->postJson('/api/v1/postbacks/bitlabs', [
            'uid' => $user->id,
            'tx' => $txId,
            'val' => $amount,
            'type' => 'COMPLETE',
            'signature' => $sig,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);

        // Assert postback recorded
        $this->assertDatabaseHas('postbacks', [
            'provider' => 'bitlabs',
            'external_transaction_id' => $txId,
            'user_id' => $user->id,
            'status' => Postback::STATUS_CREDITED,
            'is_reversal' => false,
        ]);

        // Assert ledger entry and user available balance
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertNotNull($availableAccount);
        $this->assertEquals('1500.0000', $availableAccount->balance);
    }

    public function test_postback_idempotency_replay_attack_prevents_duplicate_credits(): void
    {
        $user = User::factory()->create();
        $txId = 'tx_bl_replay_' . uniqid();
        $amount = '2000.0000';
        $secret = 'test_bitlabs_secret';
        $sig = hash_hmac('sha256', "{$user->id}:{$txId}:{$amount}", $secret);

        $payload = [
            'uid' => $user->id,
            'tx' => $txId,
            'val' => $amount,
            'type' => 'COMPLETE',
            'signature' => $sig,
        ];

        // 1st request -> processed
        $res1 = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', $payload);
        $res1->assertStatus(200);

        // 2nd request (Replay) -> returns 200 without second credit
        $res2 = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', $payload);
        $res2->assertStatus(200);

        // Postback table count must be strictly 1
        $this->assertEquals(1, Postback::where('provider', 'bitlabs')->where('external_transaction_id', $txId)->count());

        // Balance must remain 2000.0000, NOT 4000.0000
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertEquals('2000.0000', $availableAccount->balance);
    }

    public function test_invalid_signature_rejected_with_403(): void
    {
        $user = User::factory()->create();
        $txId = 'tx_bl_bogus_' . uniqid();

        $response = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', [
                'uid' => $user->id,
                'tx' => $txId,
                'val' => '1000.0000',
                'type' => 'COMPLETE',
                'signature' => 'completely_invalid_signature_hash',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['status' => 'error', 'message' => 'Invalid signature.']);

        $this->assertDatabaseMissing('postbacks', [
            'external_transaction_id' => $txId,
        ]);
    }

    public function test_unwhitelisted_ip_rejected_with_403(): void
    {
        $user = User::factory()->create();
        $txId = 'tx_bl_ip_' . uniqid();
        $secret = 'test_bitlabs_secret';
        $sig = hash_hmac('sha256', "{$user->id}:{$txId}:1000.0000", $secret);

        $response = $this->withHeaders([
            'X-Simulate-Ip' => '203.0.113.199', // Not in whitelist
        ])->postJson('/api/v1/postbacks/bitlabs', [
            'uid' => $user->id,
            'tx' => $txId,
            'val' => '1000.0000',
            'type' => 'COMPLETE',
            'signature' => $sig,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'POSTBACK_IP_UNAUTHORIZED',
        ]);
    }

    public function test_cpx_valid_credit_and_chargeback_reversal(): void
    {
        $user = User::factory()->create();
        $txId = 'cpx_tx_' . uniqid();
        $secret = 'test_cpx_hash';
        $hash = md5($txId . '-' . $secret);

        // 1. Credit 3000 points
        $response = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->get("/api/v1/postbacks/cpx?trans_id={$txId}&user_id={$user->id}&amount_local=3000&status=1&hash={$hash}");

        $response->assertStatus(200);
        $this->assertEquals('1', $response->getContent());

        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();
        $this->assertEquals('3000.0000', $availableAccount->balance);

        // 2. Reversal / Chargeback (status = 2) with a unique reversal transaction ID
        $reversalTxId = 'cpx_rev_' . uniqid();
        $revHash = md5($reversalTxId . '-' . $secret);

        $revResponse = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->get("/api/v1/postbacks/cpx?trans_id={$reversalTxId}&user_id={$user->id}&amount_local=3000&status=2&hash={$revHash}");

        $revResponse->assertStatus(200);

        // Balance should now be 0.0000
        $availableAccount->refresh();
        $this->assertEquals('0.0000', $availableAccount->balance);
    }

    public function test_offerwall_gg_reversal_creates_negative_balance_and_fraud_hold_when_user_balance_is_zero(): void
    {
        $user = User::factory()->create();
        $txId = 'owgg_rev_' . uniqid();
        $secret = 'test_owgg_secret';
        $sig = hash_hmac('sha256', "{$user->id}:{$txId}:1200", $secret);

        // Send reversal when balance is 0
        $response = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/offerwall-gg', [
                'user_id' => $user->id,
                'transaction_id' => $txId,
                'amount' => '1200',
                'type' => 'reversal',
                'signature' => $sig,
            ]);

        $response->assertStatus(200);

        // Balance must be negative: -1200.0000
        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertEquals('-1200.0000', $availableAccount->balance);

        // Fraud flag must be generated for negative balance hold
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user->id,
            'flag_type' => FraudFlag::TYPE_NEGATIVE_BALANCE_HOLD,
            'is_resolved' => false,
        ]);
    }

    public function test_offerwall_com_credit_postback(): void
    {
        $user = User::factory()->create();
        $txId = 'owcom_tx_' . uniqid();
        $secret = 'test_owcom_secret';
        $sig = hash('sha256', "{$txId}:{$secret}");

        $response = $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/offerwall-com', [
                'transId' => $txId,
                'subId' => $user->id,
                'reward' => '5000',
                'status' => 1,
                'signature' => $sig,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'ok', 'result' => 1]);

        $availableAccount = Account::where('user_id', $user->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertEquals('5000.0000', $availableAccount->balance);
    }

    public function test_referral_bonus_unlocked_when_referee_reaches_1000_points(): void
    {
        $referrer = User::factory()->create();
        $referee = User::factory()->create(['referred_by_id' => $referrer->id]);

        $secret = 'test_bitlabs_secret';

        // 1. First offer: 700 points (< 1000 threshold)
        $tx1 = 'ref_tx_1_' . uniqid();
        $sig1 = hash_hmac('sha256', "{$referee->id}:{$tx1}:700", $secret);

        $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', [
                'uid' => $referee->id,
                'tx' => $tx1,
                'val' => '700',
                'type' => 'COMPLETE',
                'signature' => $sig1,
            ])->assertStatus(200);

        // Referrer should have NO bonus yet
        $referrerAccount = Account::where('user_id', $referrer->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();
        $this->assertTrue($referrerAccount === null || bccomp($referrerAccount->balance, '0.0000', 4) === 0);

        // 2. Second offer: 500 points (Total = 1200 >= 1000 threshold)
        $tx2 = 'ref_tx_2_' . uniqid();
        $sig2 = hash_hmac('sha256', "{$referee->id}:{$tx2}:500", $secret);

        $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', [
                'uid' => $referee->id,
                'tx' => $tx2,
                'val' => '500',
                'type' => 'COMPLETE',
                'signature' => $sig2,
            ])->assertStatus(200);

        // Referrer MUST now have 500.0000 points referral bonus!
        $referrerAccount = Account::where('user_id', $referrer->id)
            ->where('type', Account::TYPE_USER_AVAILABLE)
            ->first();

        $this->assertNotNull($referrerAccount);
        $this->assertEquals('500.0000', $referrerAccount->balance);

        // 3. Third offer: 800 points -> referral bonus must NOT be duplicated
        $tx3 = 'ref_tx_3_' . uniqid();
        $sig3 = hash_hmac('sha256', "{$referee->id}:{$tx3}:800", $secret);

        $this->withHeaders(['X-Simulate-Ip' => '127.0.0.1'])
            ->postJson('/api/v1/postbacks/bitlabs', [
                'uid' => $referee->id,
                'tx' => $tx3,
                'val' => '800',
                'type' => 'COMPLETE',
                'signature' => $sig3,
            ])->assertStatus(200);

        $referrerAccount->refresh();
        $this->assertEquals('500.0000', $referrerAccount->balance);
    }

    public function test_authenticated_user_can_access_offer_providers_and_featured_offers(): void
    {
        $user = User::factory()->create();

        $responseProviders = $this->actingAs($user)
            ->getJson('/api/v1/offers/providers');

        $responseProviders->assertStatus(200);
        $responseProviders->assertJsonStructure([
            'success',
            'data' => [
                'user_id',
                'providers' => [
                    '*' => ['id', 'name', 'description', 'category', 'iframe_url'],
                ],
            ],
        ]);

        $responseFeatured = $this->actingAs($user)
            ->getJson('/api/v1/offers/featured');

        $responseFeatured->assertStatus(200);
        $responseFeatured->assertJsonStructure([
            'success',
            'data' => [
                'total_offers',
                'offers' => [
                    '*' => ['id', 'provider', 'title', 'payout_points', 'payout_usd', 'click_url'],
                ],
            ],
        ]);
    }
}
