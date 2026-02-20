<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerService
{
    /**
     * Get paginated list of customers with filtering and search.
     */
    public function list(Request $request): LengthAwarePaginator
    {
        $query = Customer::query();

        // Search by name, email, or phone
        if ($request->has('search') && $request->search) {
            $query->search($request->search);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->byStatus($request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $allowedSortFields = ['name', 'email', 'created_at'];

        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        }

        // Pagination
        $perPage = min((int) $request->get('per_page', 25), 100);

        return $query->paginate($perPage);
    }

    /**
     * Create a new customer.
     */
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    /**
     * Update an existing customer.
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    /**
     * Soft-delete a customer and their invoices.
     */
    public function delete(Customer $customer): void
    {
        // Soft-delete associated invoices
        $customer->invoices()->delete();

        // Soft-delete the customer
        $customer->delete();
    }

    /**
     * Calculate total receivable for a customer.
     */
    public function calculateReceivable(Customer $customer): float
    {
        return $customer->total_receivable;
    }
}
