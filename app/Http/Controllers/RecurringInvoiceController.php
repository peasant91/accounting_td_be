<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecurringInvoiceController extends Controller
{
    protected RecurringInvoiceService $service;

    public function __construct(RecurringInvoiceService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource for a customer.
     */
    public function index(Customer $customer): JsonResponse
    {
        $recurringInvoices = $customer->recurringInvoices()->orderBy('created_at', 'desc')->get();
        return response()->json($recurringInvoices);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'title' => 'required|string|max:255',
            'recurrence_type' => 'required|string', // Enum validation done by DB/Service implies trust or strict validation here?
            // strict validation: 'required|in:monthly,weekly,...'
            'recurrence_interval' => 'required|integer|min:1',
            'recurrence_unit' => 'nullable|string|in:day,week,month,year',
            'total_count' => 'nullable|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'line_items' => 'required|array',
            'tax_rate' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'due_date_offset' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
        ]);

        $recurringInvoice = $this->service->create($validated);

        return response()->json(['data' => new \App\Http\Resources\RecurringInvoiceResource($recurringInvoice)], 201);
    }

    /**
     * Display the specified resource.
     */

    public function show(RecurringInvoice $recurringInvoice): JsonResponse
    {
        return response()->json(['data' => new \App\Http\Resources\RecurringInvoiceResource($recurringInvoice)]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RecurringInvoice $recurringInvoice): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'recurrence_type' => 'sometimes|string',
            'recurrence_interval' => 'sometimes|integer|min:1',
            'recurrence_unit' => 'nullable|string|in:day,week,month,year',
            'total_count' => 'nullable|integer|min:1',
            'start_date' => 'sometimes|date',
            'line_items' => 'sometimes|array',
            'tax_rate' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'due_date_offset' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'sometimes|in:active,pending,completed,terminated',
        ]);

        $recurringInvoice = $this->service->update($recurringInvoice, $validated);

        return response()->json(['data' => new \App\Http\Resources\RecurringInvoiceResource($recurringInvoice)]);
    }

    /**
     * Remove the specified resource from storage (terminate).
     */
    public function destroy(RecurringInvoice $recurringInvoice): JsonResponse
    {
        // Terminate instead of delete?
        $recurringInvoice->update(['status' => 'terminated', 'next_invoice_date' => null]);
        return response()->json(['message' => 'Recurring invoice terminated']);
    }

    /**
     * Manually generate an invoice from the template.
     */
    public function manualGenerate(RecurringInvoice $recurringInvoice): JsonResponse
    {
        try {
            $invoice = $this->service->generateInvoice($recurringInvoice, true);

            if (!$invoice) {
                return response()->json(['message' => 'Could not generate invoice (template completed or terminated)'], 400);
            }

            return response()->json([
                'message' => 'Invoice generated successfully',
                'invoice_id' => $invoice->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Generation failed: ' . $e->getMessage()], 500);
        }
    }
}
