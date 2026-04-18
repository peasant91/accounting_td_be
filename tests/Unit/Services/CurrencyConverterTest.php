<?php

namespace Tests\Unit\Services;

use App\Exceptions\MissingRateException;
use App\Models\CurrencyRate;
use App\Services\CurrencyConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyConverterTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_to_base_is_identity(): void
    {
        $converter = app(CurrencyConverter::class);
        $this->assertSame(100.0, $converter->convert(100, 'IDR'));
        $this->assertSame(100.0, $converter->convert(100, 'IDR', 'IDR'));
    }

    public function test_foreign_to_base_uses_stored_rate(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $converter = app(CurrencyConverter::class);

        $this->assertSame(1_600_000.0, $converter->convert(100, 'USD'));
    }

    public function test_unknown_currency_throws(): void
    {
        $converter = app(CurrencyConverter::class);

        $this->expectException(MissingRateException::class);
        $converter->convert(1, 'SGD');
    }

    public function test_rates_map_includes_base_identity(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $map = app(CurrencyConverter::class)->ratesMap();

        $this->assertSame(1.0, $map['IDR']);
        $this->assertSame(16000.0, $map['USD']);
    }

    public function test_rates_updated_at_returns_max_timestamp(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        CurrencyRate::create(['currency' => 'JPY', 'rate_to_base' => 110]);

        $this->assertNotNull(app(CurrencyConverter::class)->ratesUpdatedAt());
    }

    public function test_rates_updated_at_null_when_empty(): void
    {
        $this->assertNull(app(CurrencyConverter::class)->ratesUpdatedAt());
    }
}
