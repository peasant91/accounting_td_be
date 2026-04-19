<?php

namespace App\Models;

use App\Services\Audit\AuditsChanges;
use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    use AuditsChanges;

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
