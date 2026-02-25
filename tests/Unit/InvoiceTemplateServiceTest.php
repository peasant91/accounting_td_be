<?php

namespace Tests\Unit;

use App\Services\InvoiceTemplateService;
use Tests\TestCase;

class InvoiceTemplateServiceTest extends TestCase
{
    public function test_getDefaultComponents_returns_config_data()
    {
        $service = new InvoiceTemplateService();
        $components = $service->getDefaultComponents();

        $this->assertIsArray($components);
        $this->assertCount(11, $components);
        $this->assertEquals('company_header', $components[0]['key']);
    }

    public function test_resolveLocale_jpy()
    {
        $service = new InvoiceTemplateService();
        $locale = $service->resolveLocale('JPY');

        $this->assertEquals('ja', $locale['language']);
        $this->assertEquals('ja-JP', $locale['locale']);
        $this->assertArrayHasKey('invoice_date', $locale['labels']);
        $this->assertEquals('請求日', $locale['labels']['invoice_date']);
    }

    public function test_resolveLocale_usd()
    {
        $service = new InvoiceTemplateService();
        $locale = $service->resolveLocale('USD');

        $this->assertEquals('en', $locale['language']);
        $this->assertEquals('en-US', $locale['locale']);
        $this->assertEquals('Invoice Date', $locale['labels']['invoice_date']);
    }

    public function test_resolveLocale_fallback()
    {
        $service = new InvoiceTemplateService();
        $locale = $service->resolveLocale('XYZ'); // Unknown currency

        $this->assertEquals('en', $locale['language']);
        $this->assertEquals('en-US', $locale['locale']);
    }
}
