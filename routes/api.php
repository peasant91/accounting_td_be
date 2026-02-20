<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

// API v1 routes
Route::prefix('v1')->group(function () {
    // Dashboard
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Customers
    Route::apiResource('customers', CustomerController::class);

    // Invoice Templates
    Route::get('/customers/{customer}/invoice-template', [InvoiceTemplateController::class, 'show']);
    Route::put('/customers/{customer}/invoice-template', [InvoiceTemplateController::class, 'update']);
    Route::get('/customers/{customer}/invoice-template/preview', [InvoiceTemplateController::class, 'preview']);

    // Invoices
    Route::apiResource('invoices', InvoiceController::class);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
    // ... existing invoice routes ...
    Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend']);
    Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder']);
    Route::post('/invoices/{invoice}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);

    // Recurring Invoices
    Route::get('/customers/{customer}/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'index']);
    Route::post('/recurring-invoices/{recurringInvoice}/generate', [\App\Http\Controllers\RecurringInvoiceController::class, 'manualGenerate']);
    Route::apiResource('recurring-invoices', \App\Http\Controllers\RecurringInvoiceController::class)->except(['index', 'create', 'edit']);
});
