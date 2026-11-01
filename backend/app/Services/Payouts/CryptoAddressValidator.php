<?php

namespace App\Services\Payouts;

class CryptoAddressValidator
{
    /**
     * Validate cryptocurrency destination address format based on asset network.
     */
    public function isValid(string $asset, string $address): bool
    {
        $address = trim($address);

        return match ($asset) {
            'USDT_TRC20' => (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address),
            'USDT_BEP20' => (bool) preg_match('/^0x[a-fA-F0-9]{40}$/', $address),
            'LTC' => (bool) preg_match('/^(L|M)[a-km-zA-HJ-NP-Z1-9]{26,33}$|^ltc1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{39,59}$/', $address),
            'BTC' => (bool) preg_match('/^(1|3)[a-km-zA-HJ-NP-Z1-9]{25,34}$|^bc1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{39,59}$/', $address),
            default => false,
        };
    }
}
