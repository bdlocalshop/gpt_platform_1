<?php

namespace App\Services\Postbacks\SignatureValidators;

use Illuminate\Http\Request;

class OfferwallGgSignatureValidator implements SignatureValidatorInterface
{
    public function validate(Request $request): bool
    {
        $secret = (string) config('services.offerwalls.offerwall_gg.secret', '');
        if (empty($secret)) {
            return false;
        }

        $providedSignature = $request->input('signature')
            ?? $request->input('hash')
            ?? $request->header('X-Offerwall-Signature')
            ?? $request->header('X-Signature');

        if (empty($providedSignature)) {
            return false;
        }

        $userId = (string) ($request->input('user_id') ?? $request->input('uid') ?? '');
        $txId = (string) ($request->input('transaction_id') ?? $request->input('trans_id') ?? $request->input('tx') ?? '');
        $amount = (string) ($request->input('amount') ?? $request->input('payout') ?? '');

        $candidates = [
            hash_hmac('sha256', "{$userId}:{$txId}:{$amount}", $secret),
            hash_hmac('sha256', $txId, $secret),
            md5($txId . ':' . $secret),
        ];

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
