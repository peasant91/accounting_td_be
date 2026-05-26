<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceTemplateController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public
    Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

    // Requires authentication
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\AuthController::class, 'me']);

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
        Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend']);
        Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder']);
        Route::post('/invoices/{invoice}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);

        // Recurring Invoices
        Route::get('/customers/{customer}/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'index']);
        Route::post('/recurring-invoices/{recurringInvoice}/generate', [\App\Http\Controllers\RecurringInvoiceController::class, 'manualGenerate']);
        Route::apiResource('recurring-invoices', \App\Http\Controllers\RecurringInvoiceController::class)->except(['index', 'create', 'edit']);

        // Currency Rates
        Route::get('/currency-rates', [\App\Http\Controllers\CurrencyRateController::class, 'index']);
        Route::put('/currency-rates/{currency}', [\App\Http\Controllers\CurrencyRateController::class, 'upsert']);

        // Item Templates
        Route::get('/item-templates', [\App\Http\Controllers\ItemTemplateController::class, 'index']);
        Route::post('/item-templates', [\App\Http\Controllers\ItemTemplateController::class, 'store']);
        Route::put('/item-templates/{itemTemplate}', [\App\Http\Controllers\ItemTemplateController::class, 'update']);
        Route::delete('/item-templates/{itemTemplate}', [\App\Http\Controllers\ItemTemplateController::class, 'destroy']);

        // Admin management (super_admin only)
        Route::middleware(['role:super_admin'])->group(function () {
            Route::apiResource('admins', \App\Http\Controllers\AdminController::class)
                ->parameters(['admins' => 'admin']);
        });

        // Audit
        Route::prefix('audit')->group(function () {
            Route::get('/activity', [\App\Http\Controllers\AuditController::class, 'activity']);
            Route::get('/login-attempts', [\App\Http\Controllers\AuditController::class, 'loginAttempts']);
        });
    });
});
