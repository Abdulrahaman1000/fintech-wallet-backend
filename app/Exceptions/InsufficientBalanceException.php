<?php

namespace App\Exceptions;

use App\Models\Transfer;
use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct(public Transfer $transfer)
    {
        parent::__construct('Insufficient balance.');
    }
}