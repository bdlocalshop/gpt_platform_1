<?php

namespace App\Services\Fraud\DTOs;

class IpInspectionResult
{
    public function __construct(
        public readonly string $ip,
        public readonly bool $isVpn = false,
        public readonly bool $isProxy = false,
        public readonly bool $isTor = false,
        public readonly bool $isDatacenter = false,
        public readonly int $riskScore = 0,
        public readonly ?string $countryCode = null,
        public readonly ?string $city = null,
        public readonly ?string $isp = null,
        public readonly ?string $asn = null,
        public readonly string $source = 'local'
    ) {}

    public function isFraudulent(): bool
    {
        return $this->isVpn || $this->isProxy || $this->isTor || $this->isDatacenter || $this->riskScore >= 67;
    }

    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'is_vpn' => $this->isVpn,
            'is_proxy' => $this->isProxy,
            'is_tor' => $this->isTor,
            'is_datacenter' => $this->isDatacenter,
            'risk_score' => $this->riskScore,
            'country_code' => $this->countryCode,
            'city' => $this->city,
            'isp' => $this->isp,
            'asn' => $this->asn,
            'source' => $this->source,
            'is_fraudulent' => $this->isFraudulent(),
        ];
    }
}
