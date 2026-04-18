<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class InvoiceNotDeletableException extends RuntimeException
{
    public function __construct(string $message = 'Only draft invoices can be deleted. Use cancel for sent invoices.', ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
