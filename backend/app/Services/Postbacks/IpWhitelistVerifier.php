<?php

namespace App\Services\Postbacks;

class IpWhitelistVerifier
{
    /**
     * Determine if an incoming client IP is permitted for the given provider.
     */
    public function isIpAllowed(string $ip, string $provider): bool
    {
        $whitelistConfig = config("services.offerwalls.{$provider}.ip_whitelist", '');
        $allowedRanges = array_filter(array_map('trim', explode(',', $whitelistConfig)));

        // Always allow localhost in testing/local unless explicitly testing whitelist blocks
        if (app()->environment(['local', 'testing']) && ($ip === '127.0.0.1' || $ip === '::1')) {
            if (in_array('127.0.0.1', $allowedRanges) || in_array('::1', $allowedRanges)) {
                return true;
            }
        }

        foreach ($allowedRanges as $range) {
            if ($this->ipMatchesRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP matches a single IP or CIDR notation (e.g. 192.168.1.0/24).
     */
    public function ipMatchesRange(string $ip, string $range): bool
    {
        if ($ip === $range) {
            return true;
        }

        if (!str_contains($range, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        // IPv4 CIDR check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnetMasked = $subnetLong & $mask;

            return ($ipLong & $mask) === $subnetMasked;
        }

        return false;
    }
}
