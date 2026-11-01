<?php

namespace App\Services\Fraud;

use App\Models\FraudFlag;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserIpLog;
use App\Services\Fraud\DTOs\IpInspectionResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AntiFraudService
{
    public function __construct(
        protected ProxyCheckClient $proxyCheck,
        protected MaxMindReader $maxMind
    ) {}

    /**
     * Inspect an IP address using ProxyCheck, MaxMind, or local private subnet filters.
     */
    public function inspectIp(string $ip): IpInspectionResult
    {
        // 1. Check for private, loopback, or local addresses
        if ($this->isPrivateOrLoopback($ip)) {
            return new IpInspectionResult(
                ip: $ip,
                isVpn: false,
                isProxy: false,
                isTor: false,
                isDatacenter: false,
                riskScore: 0,
                countryCode: 'US',
                city: 'Localhost',
                isp: 'Private Network',
                asn: 'AS0',
                source: 'local_whitelist'
            );
        }

        // 2. Query ProxyCheck.io (Live API with 24h Redis caching)
        $proxyResult = $this->proxyCheck->check($ip);
        if ($proxyResult !== null) {
            return $proxyResult;
        }

        // 3. Fallback to local MaxMind GeoLite2 databases
        $maxMindResult = $this->maxMind->check($ip);
        if ($maxMindResult !== null) {
            return $maxMindResult;
        }

        // 4. Default clean IP inspection result
        return new IpInspectionResult(
            ip: $ip,
            riskScore: 0,
            source: 'unclassified'
        );
    }

    /**
     * Audit and record every IP request with device headers and risk flags.
     */
    public function logAccess(
        Request $request,
        ?User $user,
        string $action = 'request',
        ?IpInspectionResult $inspection = null
    ): UserIpLog {
        $ip = $request->ip() ?? '127.0.0.1';
        $inspection = $inspection ?? $this->inspectIp($ip);
        $ua = $request->userAgent();
        $uaHash = $ua ? hash('sha256', $ua) : null;
        $fp = substr((string) ($request->header('X-Device-Fingerprint') ?? ''), 0, 128);

        return UserIpLog::create([
            'user_id' => $user?->id,
            'ip_address' => $ip,
            'device_fingerprint' => !empty($fp) ? $fp : null,
            'user_agent' => $ua,
            'user_agent_hash' => $uaHash,
            'is_vpn' => $inspection->isVpn,
            'is_proxy' => $inspection->isProxy,
            'is_tor' => $inspection->isTor,
            'is_datacenter' => $inspection->isDatacenter,
            'risk_score' => $inspection->riskScore,
            'country_code' => $inspection->countryCode,
            'city' => $inspection->city,
            'isp' => $inspection->isp,
            'asn' => $inspection->asn,
            'request_path' => substr($request->path(), 0, 255),
            'action' => $action,
        ]);
    }

    /**
     * Scan for multi-accounting via device fingerprint and IP subnet collisions.
     */
    public function checkForMultiAccounting(Request $request, ?User $user): array
    {
        if (!$user) {
            return [];
        }

        $collisions = [];
        $fp = $request->header('X-Device-Fingerprint');
        $ip = $request->ip() ?? '127.0.0.1';

        // 1. Device Fingerprint Collision Check
        if (!empty($fp)) {
            $matchingUserIds = UserDevice::where('device_fingerprint', $fp)
                ->where('user_id', '!=', $user->id)
                ->pluck('user_id')
                ->unique()
                ->toArray();

            if (!empty($matchingUserIds)) {
                $collisions['device_fingerprint'] = $matchingUserIds;

                // Flag the current user
                FraudFlag::firstOrCreate([
                    'user_id' => $user->id,
                    'flag_type' => FraudFlag::TYPE_MULTI_ACCOUNT_FP,
                ], [
                    'severity' => FraudFlag::SEVERITY_HIGH,
                    'details' => [
                        'device_fingerprint' => $fp,
                        'conflicting_user_ids' => $matchingUserIds,
                        'detected_at' => now()->toIso8601String(),
                    ],
                ]);

                // Also flag matching colliding users
                foreach ($matchingUserIds as $otherId) {
                    FraudFlag::firstOrCreate([
                        'user_id' => $otherId,
                        'flag_type' => FraudFlag::TYPE_MULTI_ACCOUNT_FP,
                    ], [
                        'severity' => FraudFlag::SEVERITY_HIGH,
                        'details' => [
                            'device_fingerprint' => $fp,
                            'conflicting_user_ids' => [$user->id],
                            'detected_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            }
        }

        // 2. IP Collision Check (excluding local/private subnets)
        if (!$this->isPrivateOrLoopback($ip)) {
            $recentSameIpUserIds = UserIpLog::where('ip_address', $ip)
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->pluck('user_id')
                ->filter()
                ->unique()
                ->toArray();

            if (count($recentSameIpUserIds) >= 2) {
                $collisions['ip_collision'] = $recentSameIpUserIds;

                FraudFlag::firstOrCreate([
                    'user_id' => $user->id,
                    'flag_type' => FraudFlag::TYPE_MULTI_ACCOUNT_IP,
                ], [
                    'severity' => FraudFlag::SEVERITY_MEDIUM,
                    'details' => [
                        'ip_address' => $ip,
                        'conflicting_user_ids' => $recentSameIpUserIds,
                        'detected_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        }

        return $collisions;
    }

    /**
     * Anti-Fraud Invariant: Verify that a crypto payout address is not reused across accounts.
     */
    public function checkCryptoAddressCollision(string $address, int $currentUserId): bool
    {
        $collidingRequests = PayoutRequest::where('crypto_address', $address)
            ->where('user_id', '!=', $currentUserId)
            ->get();

        if ($collidingRequests->isEmpty()) {
            return false;
        }

        $otherUserIds = $collidingRequests->pluck('user_id')->unique()->toArray();

        // Flag current account
        FraudFlag::firstOrCreate([
            'user_id' => $currentUserId,
            'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
        ], [
            'severity' => FraudFlag::SEVERITY_CRITICAL,
            'details' => [
                'crypto_address' => $address,
                'colliding_user_ids' => $otherUserIds,
            ],
        ]);

        // Flag previous accounts using the same crypto address
        foreach ($otherUserIds as $otherId) {
            FraudFlag::firstOrCreate([
                'user_id' => $otherId,
                'flag_type' => FraudFlag::TYPE_REUSED_CRYPTO_ADDRESS,
            ], [
                'severity' => FraudFlag::SEVERITY_CRITICAL,
                'details' => [
                    'crypto_address' => $address,
                    'colliding_user_ids' => [$currentUserId],
                ],
            ]);
        }

        Log::warning('CRITICAL ANTI-FRAUD: Reused cryptocurrency address attempt detected', [
            'address' => $address,
            'current_user_id' => $currentUserId,
            'colliding_user_ids' => $otherUserIds,
        ]);

        return true;
    }

    /**
     * Check if an IP is local, loopback, or private RFC1918.
     */
    public function isPrivateOrLoopback(string $ip): bool
    {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
