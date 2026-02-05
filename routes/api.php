<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')->group(function () {
    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend']);
    Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder']);
    Route::post('/invoices/{invoice}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);
});
