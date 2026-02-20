<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceSequence extends Model
{
    protected $fillable = [
        'year',
        'last_number',
    ];

    /**
     * Get the next invoice number for the given year.
     * Uses a lock to prevent race conditions.
     */
    public static function getNextNumber(int $year): string
    {
        return \DB::transaction(function () use ($year) {
            $sequence = static::lockForUpdate()->firstOrCreate(
                ['year' => $year],
                ['last_number' => 0]
            );

            $sequence->increment('last_number');

            return sprintf('INV-%d-%04d', $year, $sequence->last_number);
        });
    }
}
