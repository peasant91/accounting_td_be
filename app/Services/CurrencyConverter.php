<?php

namespace App\Services;

use App\Exceptions\MissingRateException;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class CurrencyConverter
{
    private ?array $ratesCache = null;
    private ?Carbon $ratesUpdatedAtCache = null;
    private bool $ratesUpdatedAtLoaded = false;

    public function convert(float $amount, string $from, ?string $to = null): float
    {
        $to ??= $this->baseCurrency();

        if ($from === $to) {
            return $amount;
        }

        $map = $this->ratesMap();
        if (!isset($map[$from])) {
            throw new MissingRateException($from);
        }
        if (!isset($map[$to])) {
            throw new MissingRateException($to);
        }

        // Convert: amount in $from → base → $to
        $amountInBase = $amount * $map[$from];
        return $amountInBase / $map[$to];
    }

    public function ratesMap(): array
    {
        if ($this->ratesCache !== null) {
            return $this->ratesCache;
        }

        $map = [$this->baseCurrency() => 1.0];
        foreach (CurrencyRate::all() as $row) {
            $map[$row->currency] = (float) $row->rate_to_base;
        }

        return $this->ratesCache = $map;
    }

    public function ratesUpdatedAt(): ?Carbon
    {
        if ($this->ratesUpdatedAtLoaded) {
            return $this->ratesUpdatedAtCache;
        }
        $this->ratesUpdatedAtLoaded = true;

        $max = CurrencyRate::max('updated_at');
        return $this->ratesUpdatedAtCache = $max ? Carbon::parse($max) : null;
    }

    public function knownCurrencies(): array
    {
        return array_keys($this->ratesMap());
    }

    private function baseCurrency(): string
    {
        return config('billing.base_currency', 'IDR');
    }
}
