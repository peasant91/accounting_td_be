<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerCollection;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {
    }

    /**
     * GET /customers - List all customers with pagination.
     */
    public function index(Request $request): CustomerCollection
    {
        $customers = $this->customerService->list($request);

        return new CustomerCollection($customers);
    }

    /**
     * POST /customers - Create a new customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customerService->create($request->validated());

        return response()->json([
            'data' => new CustomerResource($customer),
            'message' => 'Customer created successfully',
        ], 201);
    }

    /**
     * GET /customers/{customer} - Get customer details with invoices.
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load('invoices');

        return response()->json([
            'data' => new CustomerResource($customer),
        ]);
    }

    /**
     * PUT /customers/{customer} - Update a customer.
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customerService->update($customer, $request->validated());

        return response()->json([
            'data' => new CustomerResource($customer),
            'message' => 'Customer updated successfully',
        ]);
    }

    /**
     * DELETE /customers/{customer} - Soft-delete a customer.
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->customerService->delete($customer);

        return response()->json([
            'message' => 'Customer deleted successfully',
        ]);
    }
}
