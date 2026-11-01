<?php

namespace App\Services\Postbacks\SignatureValidators;

use Illuminate\Http\Request;

class CpxSignatureValidator implements SignatureValidatorInterface
{
    public function validate(Request $request): bool
    {
        $secureHash = (string) config('services.offerwalls.cpx.secure_hash', '');
        if (empty($secureHash)) {
            return false;
        }

        $providedHash = $request->input('hash')
            ?? $request->input('signature')
            ?? $request->header('X-CPX-Signature');

        if (empty($providedHash)) {
            return false;
        }

        $transId = (string) ($request->input('trans_id') ?? $request->input('tx') ?? $request->input('external_transaction_id') ?? '');
        $userId = (string) ($request->input('user_id') ?? $request->input('uid') ?? '');

        $candidates = [
            md5($transId . '-' . $secureHash),
            md5($transId . $secureHash),
            md5($userId . '-' . $secureHash),
            hash_hmac('sha256', $transId, $secureHash),
        ];

        foreach ($candidates as $candidate) {
            if (hash_equals(strtolower($candidate), strtolower($providedHash))) {
                return true;
            }
        }

        return false;
    }
}
