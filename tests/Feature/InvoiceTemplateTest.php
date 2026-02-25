<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InvoiceTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class InvoiceTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a user if auth is required, but currently it's open or basic auth depending on middleware
        // Design says: "No additional auth needed for single-tenant admin-only usage."
        // But likely we need to be authenticated as a user if `auth:sanctum` is used.
        // I'll assume public for now as per `routes/api.php` usually default middleware apply.
        // Wait, typical Laravel install has auth middleware.
        // Routes are in `routes/api.php` under `v1`. Usually no auth by default unless specified.
        // I'll check `routes/api.php` again.
        // It's `Route::prefix('v1')->group(...)`, no middleware group.
    }

    public function test_get_invoice_template_returns_default_when_none_exists()
    {
        $customer = Customer::factory()->create(['currency' => 'USD']);

        $response = $this->getJson("/api/v1/customers/{$customer->id}/invoice-template");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'customer_id',
                    'components',
                    'resolved_locale',
                ]
            ])
            ->assertJson([
                'data' => [
                    'customer_id' => $customer->id,
                    'id' => null,
                ]
            ]);

        // Validating default component count (9)
        $this->assertCount(11, $response->json('data.components'));
    }

    public function test_create_new_invoice_template()
    {
        $customer = Customer::factory()->create(['currency' => 'JPY']);

        $components = config('invoice.default_components');
        // Toggle off optional component
        foreach ($components as &$comp) {
            if ($comp['key'] === 'bank_transfer') {
                $comp['enabled'] = false;
            }
        }

        $payload = ['components' => $components];

        $response = $this->putJson("/api/v1/customers/{$customer->id}/invoice-template", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.components.7.enabled', false); // Bank transfer is 8th item (index 7)

        $this->assertDatabaseHas('invoice_templates', [
            'customer_id' => $customer->id,
        ]);

        $template = InvoiceTemplate::where('customer_id', $customer->id)->first();
        $this->assertFalse($template->components[7]['enabled']);
    }

    public function test_update_existing_invoice_template()
    {
        $customer = Customer::factory()->create();
        $template = InvoiceTemplate::create([
            'customer_id' => $customer->id,
            'components' => [['key' => 'bank_transfer', 'enabled' => false]]
        ]);

        $components = config('invoice.default_components');
        // Re-enable it
        foreach ($components as &$comp) {
            $comp['enabled'] = true;
        }

        $response = $this->putJson("/api/v1/customers/{$customer->id}/invoice-template", ['components' => $components]);

        $response->assertStatus(200);

        $template->refresh();
        // Since we save full component list in logic or just diff?
        // Service saves what we pass.
        // We passed full list.
        // actually config order matters.
        // Let's just check the DB has it enabled.
        $this->assertTrue(collect($template->components)->firstWhere('key', 'bank_transfer')['enabled']);
    }

    public function test_cannot_disable_required_component()
    {
        $customer = Customer::factory()->create();

        $components = config('invoice.default_components');
        // Try to disable 'company_header' which is required
        foreach ($components as &$comp) {
            if ($comp['key'] === 'company_header') {
                $comp['enabled'] = false;
            }
        }

        $response = $this->putJson("/api/v1/customers/{$customer->id}/invoice-template", ['components' => $components]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['components']);
    }

    public function test_preview_returns_correct_currency_format_jpy()
    {
        $customer = Customer::factory()->create(['currency' => 'JPY']);

        $response = $this->getJson("/api/v1/customers/{$customer->id}/invoice-template/preview");

        $response->assertStatus(200)
            ->assertJsonPath('data.locale.language', 'ja')
            ->assertJsonPath('data.sample_invoice.currency', 'JPY');

        // Check label
        $this->assertEquals('請求日', $response->json('data.locale.labels.invoice_date'));
    }

    public function test_preview_returns_correct_currency_format_usd()
    {
        $customer = Customer::factory()->create(['currency' => 'USD']);

        $response = $this->getJson("/api/v1/customers/{$customer->id}/invoice-template/preview");

        $response->assertStatus(200)
            ->assertJsonPath('data.locale.language', 'en')
            ->assertJsonPath('data.sample_invoice.currency', 'USD');

        // Check label
        $this->assertEquals('Invoice Date', $response->json('data.locale.labels.invoice_date'));
    }
}
