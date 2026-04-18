<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];

    public static function getNextNumber(int $year): string
    {
        return DB::transaction(function () use ($year) {
            $sequence = static::lockForUpdate()->firstOrCreate(
                ['year' => $year],
                ['last_number' => 0]
            );

            $sequence->increment('last_number');

            return sprintf(
                config('billing.invoice_number.format'),
                config('billing.invoice_number.prefix'),
                $year,
                $sequence->last_number
            );
        });
    }
}
