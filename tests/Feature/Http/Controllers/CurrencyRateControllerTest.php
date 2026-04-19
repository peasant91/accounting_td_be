<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyRateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_index_returns_all_rates_and_base_currency(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        CurrencyRate::create(['currency' => 'JPY', 'rate_to_base' => 110]);

        $res = $this->getJson('/api/v1/currency-rates')->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertSame('IDR', $res->json('base_currency'));
    }

    public function test_upsert_creates_new_rate(): void
    {
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 16250])->assertOk();

        $this->assertDatabaseHas('currency_rates', ['currency' => 'USD', 'rate_to_base' => '16250.0000000000']);
    }

    public function test_upsert_updates_existing_rate(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);

        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 16250])->assertOk();

        $this->assertSame('16250.0000000000', (string) CurrencyRate::find('USD')->rate_to_base);
    }

    public function test_upsert_rejects_base_currency(): void
    {
        $this->putJson('/api/v1/currency-rates/IDR', ['rate_to_base' => 1])
            ->assertUnprocessable();
    }

    public function test_upsert_rejects_invalid_code(): void
    {
        $this->putJson('/api/v1/currency-rates/us', ['rate_to_base' => 16000])
            ->assertUnprocessable();
    }

    public function test_upsert_rejects_zero_or_negative_rate(): void
    {
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => 0])
            ->assertUnprocessable();
        $this->putJson('/api/v1/currency-rates/USD', ['rate_to_base' => -1])
            ->assertUnprocessable();
    }
}
