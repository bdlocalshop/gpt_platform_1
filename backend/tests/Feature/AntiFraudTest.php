<?php

namespace Tests\Feature;

use App\Models\FraudFlag;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserIpLog;
use App\Services\Fraud\AntiFraudService;
use App\Services\Fraud\DTOs\IpInspectionResult;
use App\Services\Fraud\ProxyCheckClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AntiFraudTest extends TestCase
{
    use RefreshDatabase;

    protected AntiFraudService $antiFraud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->antiFraud = app(AntiFraudService::class);
    }

    public function test_registration_is_blocked_when_vpn_detected(): void
    {
        $payload = [
            'name' => 'VPN Fraudster',
            'username' => 'vpnfraud',
            'email' => 'vpn@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->withHeaders([
            'X-Simulate-Fraud' => 'vpn',
            'X-Device-Fingerprint' => hash('sha256', 'vpn-device'),
        ])->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'FRAUD_IP_BLOCKED',
            ]);

        $this->assertDatabaseMissing('users', ['email' => 'vpn@example.com']);
        $this->assertDatabaseHas('user_ip_logs', [
            'action' => 'blocked_registration',
            'is_vpn' => true,
        ]);
    }

    public function test_registration_is_blocked_when_tor_exit_node_detected(): void
    {
        $payload = [
            'name' => 'Tor User',
            'username' => 'toruser',
            'email' => 'tor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->withHeaders([
            'X-Simulate-Fraud' => 'tor',
        ])->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'FRAUD_IP_BLOCKED',
            ]);

        $this->assertStringContainsString('Tor exit node', $response->json('message'));
        $this->assertDatabaseHas('user_ip_logs', [
            'action' => 'blocked_registration',
            'is_tor' => true,
        ]);
    }

    public function test_registration_is_blocked_when_datacenter_ip_detected(): void
    {
        $payload = [
            'name' => 'Datacenter Bot',
            'username' => 'datacenterbot',
            'email' => 'bot@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->withHeaders([
            'X-Simulate-Fraud' => 'datacenter',
        ])->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'FRAUD_IP_BLOCKED',
            ]);

        $this->assertStringContainsString('Datacenter network', $response->json('message'));
    }

    public function test_clean_connection_passes_and_logs_ip_access(): void
    {
        $payload = [
            'name' => 'Legit User',
            'username' => 'legituser',
            'email' => 'legit@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $fingerprint = hash('sha256', 'legit-browser-fingerprint');

        $response = $this->withHeaders([
            'X-Device-Fingerprint' => $fingerprint,
        ])->postJson('/api/v1/auth/register', $payload);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('user_ip_logs', [
            'device_fingerprint' => $fingerprint,
            'action' => 'registration',
            'is_vpn' => false,
        ]);
    }

    public function test_multi_accounting_by_device_fingerprint_flags_accounts(): void
    {
        $sharedFingerprint = hash('sha256', 'shared-family-device-123');

        // User A registers with shared fingerprint
        $userA = User::create([
            'name' => 'User Alpha',
            'username' => 'useralpha',
            'email' => 'alpha@example.com',
            'password' => Hash::make('Secret123!'),
            'registration_fingerprint' => $sharedFingerprint,
        ]);
        UserDevice::create([
            'user_id' => $userA->id,
            'device_fingerprint' => $sharedFingerprint,
            'client_platform' => 'web',
            'ip_address' => '192.168.1.50',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        // User B registers with identical fingerprint
        $userB = User::create([
            'name' => 'User Beta',
            'username' => 'userbeta',
            'email' => 'beta@example.com',
            'password' => Hash::make('Secret123!'),
            'registration_fingerprint' => $sharedFingerprint,
        ]);
        UserDevice::create([
            'user_id' => $userB->id,
            'device_fingerprint' => $sharedFingerprint,
            'client_platform' => 'web',
            'ip_address' => '192.168.1.50',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $request = request();
        $request->headers->set('X-Device-Fingerprint', $sharedFingerprint);

        $collisions = $this->antiFraud->checkForMultiAccounting($request, $userB);

        $this->assertArrayHasKey('device_fingerprint', $collisions);
        $this->assertContains($userA->id, $collisions['device_fingerprint']);

        // Assert fraud flags were created for both User B and User A
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $userB->id,
            'flag_type' => FraudFlag::TYPE_MULTI_ACCOUNT_FP,
            'severity' => FraudFlag::SEVERITY_HIGH,
        ]);

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $userA->id,
            'flag_type' => FraudFlag::TYPE_MULTI_ACCOUNT_FP,
            'severity' => FraudFlag::SEVERITY_HIGH,
        ]);
    }

    public function test_crypto_address_collision_flags_both_accounts(): void
    {
        $user1 = User::create([
            'name' => 'User One',
            'username' => 'userone',
            'email' => 'userone@example.com',
            'password' => Hash::make('Pass123!'),
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'username' => 'usertwo',
            'email' => 'usertwo@example.com',
            'password' => Hash::make('Pass123!'),
        ]);

        $reusedAddress = 'LTCPAYOUT998877665544332211';

        // User 1 submitted a payout request with this address
        PayoutRequest::create([
            'uuid' => 'payout-uuid-1',
            'user_id' => $user1->id,
            'asset' => 'LTC',
            'crypto_address' => $reusedAddress,
            'points_amount' => '5000.0000',
            'usd_amount' => '5.0000',
            'ip_address' => '127.0.0.1',
        ]);

        // User 2 attempts to use the same address
        $isColliding = $this->antiFraud->checkCryptoAddressCollision($reusedAddress, $user2->id);

        $this->assertTrue($isColliding);

        // Verify both accounts are flagged with critical severity
        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user2->id,
            'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
            'severity' => FraudFlag::SEVERITY_CRITICAL,
        ]);

        $this->assertDatabaseHas('fraud_flags', [
            'user_id' => $user1->id,
            'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
            'severity' => FraudFlag::SEVERITY_CRITICAL,
        ]);
    }

    public function test_proxycheck_client_live_query_and_cache(): void
    {
        Config::set('services.proxycheck.key', 'mock_test_key');

        $ip = '198.51.100.42';

        Http::fake([
            "https://proxycheck.io/v2/{$ip}*" => Http::response([
                'status' => 'ok',
                $ip => [
                    'proxy' => 'yes',
                    'type' => 'VPN',
                    'risk' => 95,
                    'isocode' => 'US',
                    'country' => 'United States',
                    'city' => 'Chicago',
                    'asn' => 'AS9009',
                    'provider' => 'Mullvad VPN',
                ],
            ], 200),
        ]);

        /** @var ProxyCheckClient $client */
        $client = app(ProxyCheckClient::class);

        // 1st call triggers HTTP request
        $result1 = $client->check($ip);
        $this->assertNotNull($result1);
        $this->assertTrue($result1->isVpn);
        $this->assertEquals(95, $result1->riskScore);
        $this->assertEquals('Chicago', $result1->city);
        $this->assertEquals('Mullvad VPN', $result1->isp);

        // 2nd call should hit cache without calling external API again
        Http::fake([
            "https://proxycheck.io/v2/{$ip}*" => Http::response([], 500),
        ]);

        $result2 = $client->check($ip);
        $this->assertNotNull($result2);
        $this->assertEquals(95, $result2->riskScore);
        $this->assertEquals('Chicago', $result2->city);
    }

    public function test_fraud_inspect_ip_endpoint(): void
    {
        $response = $this->getJson('/api/v1/fraud/inspect-ip');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'IP Risk Inspection Result',
            ])
            ->assertJsonStructure([
                'data' => [
                    'ip',
                    'is_vpn',
                    'is_proxy',
                    'is_tor',
                    'is_datacenter',
                    'risk_score',
                    'source',
                    'is_fraudulent',
                ],
            ]);
    }
}
