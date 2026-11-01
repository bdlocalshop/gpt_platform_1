<?php

namespace App\Services\Postbacks\SignatureValidators;

use Illuminate\Http\Request;

class BitLabsSignatureValidator implements SignatureValidatorInterface
{
    public function validate(Request $request): bool
    {
        $secret = (string) config('services.offerwalls.bitlabs.secret', '');
        if (empty($secret)) {
            return false;
        }

        $providedSignature = $request->header('X-Bitlabs-Signature')
            ?? $request->input('hash')
            ?? $request->input('signature');

        if (empty($providedSignature)) {
            return false;
        }

        $tx = (string) ($request->input('tx') ?? $request->input('trans_id') ?? $request->input('external_transaction_id') ?? '');
        $uid = (string) ($request->input('uid') ?? $request->input('user_id') ?? '');
        $val = (string) ($request->input('val') ?? $request->input('amount') ?? '');

        // Check against supported hash signatures using constant-time comparison
        $candidates = [
            hash_hmac('sha256', $tx, $secret),
            md5($tx . $secret),
            hash_hmac('sha256', "{$uid}:{$tx}:{$val}", $secret),
        ];

        // Also check raw payload HMAC if header was passed
        if ($request->getContent()) {
            $candidates[] = hash_hmac('sha256', $request->getContent(), $secret);
        }

        foreach ($candidates as $candidate) {
            if (hash_equals(strtolower($candidate), strtolower($providedSignature))) {
                return true;
            }
        }

        return false;
    }
}
