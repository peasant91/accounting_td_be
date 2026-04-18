<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest;
use App\Http\Resources\RecurringInvoiceResource;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\JsonResponse;

class RecurringInvoiceController extends Controller
{
    public function __construct(
        protected RecurringInvoiceService $service
    ) {
    }

    public function index(Customer $customer): JsonResponse
    {
        $recurringInvoices = $customer->recurringInvoices()->latest()->get();

        return response()->json(
            RecurringInvoiceResource::collection($recurringInvoices)->resolve()
        );
    }

    public function store(StoreRecurringInvoiceRequest $request): JsonResponse
    {
        $recurringInvoice = $this->service->create($request->validated());

        return response()->json([
            'data' => new RecurringInvoiceResource($recurringInvoice),
        ], 201);
    }

    public function show(RecurringInvoice $recurringInvoice): JsonResponse
    {
        return response()->json([
            'data' => new RecurringInvoiceResource($recurringInvoice),
        ]);
    }

    public function update(UpdateRecurringInvoiceRequest $request, RecurringInvoice $recurringInvoice): JsonResponse
    {
        $recurringInvoice = $this->service->update($recurringInvoice, $request->validated());

        return response()->json([
            'data' => new RecurringInvoiceResource($recurringInvoice),
        ]);
    }

    public function destroy(RecurringInvoice $recurringInvoice): JsonResponse
    {
        $this->service->terminate($recurringInvoice);

        return response()->json(['message' => 'Recurring invoice terminated']);
    }

    public function manualGenerate(RecurringInvoice $recurringInvoice): JsonResponse
    {
        $invoice = $this->service->generateInvoice($recurringInvoice, true);

        if (!$invoice) {
            return response()->json(
                ['message' => 'Could not generate invoice (template completed or terminated)'],
                400
            );
        }

        return response()->json([
            'message' => 'Invoice generated successfully',
            'invoice_id' => $invoice->id,
        ]);
    }
}
