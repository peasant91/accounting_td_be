<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('type', ['manual', 'recurring'])->default('manual')->after('status');
            $table->date('due_date')->nullable()->change();
            $table->foreignId('recurring_invoice_id')->nullable()->constrained('recurring_invoices')->nullOnDelete()->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['recurring_invoice_id']);
            $table->dropColumn('recurring_invoice_id');
            // Revert type column
            $table->dropColumn('type');
            // Revert due_date to not nullable (careful if data exists with nulls)
            // Ideally we check if we can revert, for now assume safe or just leave it nullable
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
