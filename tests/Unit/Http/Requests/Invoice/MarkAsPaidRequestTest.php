<?php

namespace Tests\Unit\Http\Requests\Invoice;

use PHPUnit\Framework\TestCase;

class MarkAsPaidRequestTest extends TestCase
{
    public function test_rules(): void
    {
        $request = new \App\Http\Requests\Invoice\MarkAsPaidRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('payment_proof', $rules);
        $this->assertContains('nullable', $rules['payment_proof']);
        $this->assertContains('file', $rules['payment_proof']);
        $this->assertContains('mimes:pdf,jpg,jpeg,png', $rules['payment_proof']);
        $this->assertContains('max:5120', $rules['payment_proof']);
    }
}
