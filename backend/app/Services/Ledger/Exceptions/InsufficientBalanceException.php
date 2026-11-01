<?php

namespace App\Services\Ledger\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(string $message = 'Insufficient available balance to perform this operation.')
    {
        parent::__construct($message, 422);
    }
}
