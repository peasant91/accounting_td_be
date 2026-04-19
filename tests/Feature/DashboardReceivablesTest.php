<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\CurrencyRate;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardReceivablesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    private function invoice(string $currency, float $total, InvoiceStatus $status): Invoice
    {
        $customer = Customer::factory()->create(['currency' => $currency]);
        return Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-' . uniqid(),
            'currency' => $currency,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'tax_rate' => 0,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'status' => $status,
            'type' => InvoiceType::Manual,
        ]);
    }

    public function test_receivables_payload_is_structured(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $this->invoice('IDR', 5_000_000, InvoiceStatus::Sent);
        $this->invoice('USD', 1_000, InvoiceStatus::Overdue);
        $this->invoice('IDR', 100_000, InvoiceStatus::Paid);  // excluded — paid

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertSame('IDR', $tr['base_currency']);
        $this->assertEqualsWithDelta(5_000_000 + (1_000 * 16000), $tr['base_total'], 0.001);
        $this->assertCount(2, $tr['breakdown']);
        $this->assertSame([], $tr['missing_rates']);
    }

    public function test_missing_rate_is_reported_and_excluded_from_base_total(): void
    {
        CurrencyRate::create(['currency' => 'USD', 'rate_to_base' => 16000]);
        $this->invoice('USD', 1_000, InvoiceStatus::Sent);
        $this->invoice('JPY', 500_000, InvoiceStatus::Sent);  // no rate

        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertEqualsWithDelta(1_000 * 16000, $tr['base_total'], 0.001);
        $this->assertSame(['JPY'], $tr['missing_rates']);
        $jpyRow = collect($tr['breakdown'])->firstWhere('currency', 'JPY');
        $this->assertNull($jpyRow['base_equivalent']);
        $this->assertSame(500000.0, $jpyRow['amount']);
    }

    public function test_empty_when_no_unpaid_invoices(): void
    {
        $res = $this->getJson('/api/v1/dashboard/summary')->assertOk()->json();
        $tr = $res['data']['total_receivables'];

        $this->assertSame(0.0, $tr['base_total']);
        $this->assertSame([], $tr['breakdown']);
        $this->assertSame([], $tr['missing_rates']);
    }
}
