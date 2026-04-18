<?php

namespace App\Exceptions;

use RuntimeException;

class MissingRateException extends RuntimeException
{
    public function __construct(public readonly string $currency)
    {
        parent::__construct("No exchange rate set for currency: {$currency}");
    }
}
