<?php

namespace App\Services\Fraud;

use App\Services\Fraud\DTOs\IpInspectionResult;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Log;

class MaxMindReader
{
    protected ?string $dbDirectory;

    public function __construct()
    {
        $this->dbDirectory = config('services.maxmind.db_path', storage_path('app/geoip'));
    }

    /**
     * Inspect an IP using local MaxMind MMDB databases.
     */
    public function check(string $ip): ?IpInspectionResult
    {
        if (empty($this->dbDirectory) || !is_dir($this->dbDirectory)) {
            return null;
        }

        $cityDb = $this->dbDirectory . DIRECTORY_SEPARATOR . 'GeoLite2-City.mmdb';
        $countryDb = $this->dbDirectory . DIRECTORY_SEPARATOR . 'GeoLite2-Country.mmdb';
        $asnDb = $this->dbDirectory . DIRECTORY_SEPARATOR . 'GeoLite2-ASN.mmdb';
        $anonymousDb = $this->dbDirectory . DIRECTORY_SEPARATOR . 'GeoIP2-Anonymous-IP.mmdb';

        $countryCode = null;
        $city = null;
        $isp = null;
        $asn = null;
        $isVpn = false;
        $isProxy = false;
        $isTor = false;
        $isDatacenter = false;

        $hasRead = false;

        // 1. Read City / Country
        try {
            if (file_exists($cityDb)) {
                $reader = new Reader($cityDb);
                $record = $reader->city($ip);
                $countryCode = $record->country->isoCode;
                $city = $record->city->name;
                $hasRead = true;
            } elseif (file_exists($countryDb)) {
                $reader = new Reader($countryDb);
                $record = $reader->country($ip);
                $countryCode = $record->country->isoCode;
                $hasRead = true;
            }
        } catch (\Throwable) {
            // Address not found or private IP
        }

        // 2. Read ASN
        try {
            if (file_exists($asnDb)) {
                $asnReader = new Reader($asnDb);
                $asnRecord = $asnReader->asn($ip);
                $asn = 'AS' . $asnRecord->autonomousSystemNumber;
                $isp = $asnRecord->autonomousSystemOrganization;
                $hasRead = true;
            }
        } catch (\Throwable) {
            // Address not found
        }

        // 3. Read Anonymous IP / Hosting (if premium database available)
        try {
            if (file_exists($anonymousDb)) {
                $anonReader = new Reader($anonymousDb);
                $anonRecord = $anonReader->anonymousIp($ip);
                $isVpn = (bool) $anonRecord->isVpn;
                $isTor = (bool) $anonRecord->isTorExitNode;
                $isProxy = (bool) ($anonRecord->isAnonymous || $anonRecord->isPublicProxy);
                $isDatacenter = (bool) $anonRecord->isHostingProvider;
                $hasRead = true;
            }
        } catch (\Throwable) {
            // Address not found
        }

        if (!$hasRead) {
            return null;
        }

        $riskScore = 0;
        if ($isTor) $riskScore = 100;
        elseif ($isVpn) $riskScore = 90;
        elseif ($isDatacenter) $riskScore = 85;
        elseif ($isProxy) $riskScore = 80;

        return new IpInspectionResult(
            ip: $ip,
            isVpn: $isVpn,
            isProxy: $isProxy,
            isTor: $isTor,
            isDatacenter: $isDatacenter,
            riskScore: $riskScore,
            countryCode: $countryCode,
            city: $city,
            isp: $isp,
            asn: $asn,
            source: 'maxmind'
        );
    }
}
