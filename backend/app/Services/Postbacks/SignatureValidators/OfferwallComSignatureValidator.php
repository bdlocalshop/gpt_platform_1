<?php

namespace App\Services\Postbacks\SignatureValidators;

use Illuminate\Http\Request;

class OfferwallComSignatureValidator implements SignatureValidatorInterface
{
    public function validate(Request $request): bool
    {
        $secret = (string) config('services.offerwalls.offerwall_com.secret', '');
        if (empty($secret)) {
            return false;
        }

        $providedSignature = $request->input('signature')
            ?? $request->input('hash')
            ?? $request->header('X-Signature');

        if (empty($providedSignature)) {
            return false;
        }

        $transId = (string) ($request->input('transId')
            ?? $request->input('transaction_id')
            ?? $request->input('trans_id')
            ?? $request->input('tx')
            ?? '');

        $subId = (string) ($request->input('subId')
            ?? $request->input('user_id')
            ?? $request->input('uid')
            ?? '');

        $candidates = [
            hash('sha256', "{$transId}:{$secret}"),
            hash_hmac('sha256', $transId, $secret),
            hash('sha256', "{$subId}:{$transId}:{$secret}"),
            md5("{$transId}:{$secret}"),
            md5($transId . $secret),
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
