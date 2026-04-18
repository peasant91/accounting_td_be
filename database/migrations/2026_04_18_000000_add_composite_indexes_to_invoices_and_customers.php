<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'invoices_status_due_date_index');
            $table->index(['customer_id', 'status'], 'invoices_customer_id_status_index');
            $table->index('type', 'invoices_type_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('name', 'customers_name_index');
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->index(['status', 'next_invoice_date'], 'recurring_invoices_status_next_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_due_date_index');
            $table->dropIndex('invoices_customer_id_status_index');
            $table->dropIndex('invoices_type_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_name_index');
        });

        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropIndex('recurring_invoices_status_next_index');
        });
    }
};
