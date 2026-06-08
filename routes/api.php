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
        // Auth — all roles
        Route::post('/logout', [\App\Http\Controllers\AuthController::class, 'logout']);
        Route::get('/me', [\App\Http\Controllers\AuthController::class, 'me']);

        // Dashboard — all roles
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

        // Customers — full CRUD for all roles
        Route::apiResource('customers', CustomerController::class);

        // Invoices — read-only for all roles
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf']);

        // Invoice Templates — read-only for all roles
        Route::get('/customers/{customer}/invoice-template', [InvoiceTemplateController::class, 'show']);
        Route::get('/customers/{customer}/invoice-template/preview', [InvoiceTemplateController::class, 'preview']);

        // Recurring Invoices — read-only for all roles
        Route::get('/customers/{customer}/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'index']);
        Route::get('/recurring-invoices/{recurringInvoice}', [\App\Http\Controllers\RecurringInvoiceController::class, 'show']);

        // Admin + SuperAdmin: invoice writes, recurring writes, template writes, settings
        Route::middleware(['role:super_admin,admin'])->group(function () {
            // Invoice writes
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::put('/invoices/{invoice}', [InvoiceController::class, 'update']);
            Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy']);
            Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);
            Route::post('/invoices/{invoice}/resend', [InvoiceController::class, 'resend']);
            Route::post('/invoices/{invoice}/send-reminder', [InvoiceController::class, 'sendReminder']);
            Route::post('/invoices/{invoice}/mark-as-paid', [InvoiceController::class, 'markAsPaid']);
            Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

            // Invoice Template write
            Route::put('/customers/{customer}/invoice-template', [InvoiceTemplateController::class, 'update']);

            // Recurring Invoice writes
            Route::post('/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'store']);
            Route::put('/recurring-invoices/{recurringInvoice}', [\App\Http\Controllers\RecurringInvoiceController::class, 'update']);
            Route::delete('/recurring-invoices/{recurringInvoice}', [\App\Http\Controllers\RecurringInvoiceController::class, 'destroy']);
            Route::post('/recurring-invoices/{recurringInvoice}/generate', [\App\Http\Controllers\RecurringInvoiceController::class, 'manualGenerate']);

            // Currency Rates
            Route::get('/currency-rates', [\App\Http\Controllers\CurrencyRateController::class, 'index']);
            Route::put('/currency-rates/{currency}', [\App\Http\Controllers\CurrencyRateController::class, 'upsert']);

            // Item Templates
            Route::get('/item-templates', [\App\Http\Controllers\ItemTemplateController::class, 'index']);
            Route::post('/item-templates', [\App\Http\Controllers\ItemTemplateController::class, 'store']);
            Route::put('/item-templates/{itemTemplate}', [\App\Http\Controllers\ItemTemplateController::class, 'update']);
            Route::delete('/item-templates/{itemTemplate}', [\App\Http\Controllers\ItemTemplateController::class, 'destroy']);
        });

        // SuperAdmin only
        Route::middleware(['role:super_admin'])->group(function () {
            Route::apiResource('admins', \App\Http\Controllers\AdminController::class)
                ->parameters(['admins' => 'admin']);

            Route::prefix('audit')->group(function () {
                Route::get('/activity', [\App\Http\Controllers\AuditController::class, 'activity']);
                Route::get('/login-attempts', [\App\Http\Controllers\AuditController::class, 'loginAttempts']);
            });
        });
    });
});
