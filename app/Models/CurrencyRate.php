<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $primaryKey = 'currency';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'currency',
        'rate_to_base',
    ];

    protected $casts = [
        'rate_to_base' => 'decimal:10',
    ];
}
