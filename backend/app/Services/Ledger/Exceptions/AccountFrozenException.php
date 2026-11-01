<?php

namespace App\Services\Ledger\Exceptions;

use Exception;

class AccountFrozenException extends Exception
{
    public function __construct(string $code)
    {
        parent::__construct("Account {$code} is frozen or closed. Mutations are rejected.", 403);
    }
}
