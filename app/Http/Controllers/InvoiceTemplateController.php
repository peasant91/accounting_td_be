<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceTemplateRequest;
use App\Models\Customer;
use App\Services\InvoiceTemplateService;
use Illuminate\Http\JsonResponse;

class InvoiceTemplateController extends Controller
{
    protected InvoiceTemplateService $service;

    public function __construct(InvoiceTemplateService $service)
    {
        $this->service = $service;
    }

    /**
     * Display the invoice template for the customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $data = $this->service->getTemplateForCustomer($customer);
        return response()->json(['data' => $data]);
    }

    /**
     * Update the invoice template for the customer.
     */
    public function update(UpdateInvoiceTemplateRequest $request, Customer $customer): JsonResponse
    {
        $template = $this->service->saveTemplate($customer, $request->input('components'));

        // Return the full resolved template data
        $data = $this->service->getTemplateForCustomer($customer);

        return response()->json([
            'data' => $data,
            'message' => 'Invoice template saved successfully',
        ]);
    }

    /**
     * Preview the invoice for the customer.
     */
    public function preview(Customer $customer): JsonResponse
    {
        $data = $this->service->getPreviewData($customer);
        return response()->json(['data' => $data]);
    }
}
