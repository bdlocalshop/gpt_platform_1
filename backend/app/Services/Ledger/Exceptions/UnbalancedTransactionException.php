<?php

namespace App\Services\Ledger\Exceptions;

use Exception;

class UnbalancedTransactionException extends Exception
{
    public function __construct(string $debits, string $credits)
    {
        parent::__construct(
            "Transaction is unbalanced: Total Debits ({$debits}) must strictly equal Total Credits ({$credits}).",
            500
        );
    }
}
