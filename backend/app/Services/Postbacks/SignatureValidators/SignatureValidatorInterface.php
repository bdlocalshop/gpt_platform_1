<?php

namespace App\Services\Postbacks\SignatureValidators;

use Illuminate\Http\Request;

interface SignatureValidatorInterface
{
    /**
     * Validate the cryptographic signature or secret hash of an incoming postback request.
     */
    public function validate(Request $request): bool;
}
