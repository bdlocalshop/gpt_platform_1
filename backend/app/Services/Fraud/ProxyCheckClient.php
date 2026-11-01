<?php

namespace App\Services\Fraud;

use App\Services\Fraud\DTOs\IpInspectionResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProxyCheckClient
{
    protected ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.proxycheck.key');
    }

    /**
     * Inspect an IP address using ProxyCheck.io API with Redis/cache caching.
     */
    public function check(string $ip): ?IpInspectionResult
    {
        $cacheKey = "fraud:proxycheck:{$ip}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($ip) {
            return $this->fetchFromApi($ip);
        });
    }

    protected function fetchFromApi(string $ip): ?IpInspectionResult
    {
        if (empty($this->apiKey)) {
            return null; // ProxyCheck not configured; defer to MaxMind or local rules
        }

        try {
            $url = "https://proxycheck.io/v2/{$ip}";
            $response = Http::timeout(4)->get($url, [
                'key' => $this->apiKey,
                'vpn' => 1,
                'asn' => 1,
                'risk' => 1,
                'port' => 1,
                'seen' => 1,
                'days' => 7,
            ]);

            if (!$response->successful()) {
                Log::warning("ProxyCheck API returned non-200 status ({$response->status()}) for IP {$ip}");
                return null;
            }

            $json = $response->json();

            if (!isset($json['status']) || $json['status'] !== 'ok' || !isset($json[$ip])) {
                return null;
            }

            $data = $json[$ip];
            $isProxy = ($data['proxy'] ?? 'no') === 'yes';
            $type = strtoupper($data['type'] ?? '');

            $isVpn = $isProxy && (str_contains($type, 'VPN') || $type === 'VPN');
            $isTor = $isProxy && (str_contains($type, 'TOR') || $type === 'TOR');
            $isDatacenter = $isProxy && (str_contains($type, 'HOSTING') || str_contains($type, 'DATA CENTER') || str_contains($type, 'BUSINESS'));
            $riskScore = (int) ($data['risk'] ?? ($isProxy ? 90 : 0));

            return new IpInspectionResult(
                ip: $ip,
                isVpn: $isVpn,
                isProxy: $isProxy,
                isTor: $isTor,
                isDatacenter: $isDatacenter,
                riskScore: $riskScore,
                countryCode: $data['isocode'] ?? null,
                city: $data['city'] ?? null,
                isp: $data['provider'] ?? null,
                asn: $data['asn'] ?? null,
                source: 'proxycheck'
            );
        } catch (\Throwable $e) {
            Log::error("ProxyCheck inspection error for {$ip}: " . $e->getMessage());
            return null;
        }
    }
}
