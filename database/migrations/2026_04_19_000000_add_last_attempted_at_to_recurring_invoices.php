<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->timestamp('last_attempted_at')->nullable()->after('last_generated_at');
            $table->index('last_attempted_at', 'recurring_invoices_last_attempted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_invoices', function (Blueprint $table) {
            $table->dropIndex('recurring_invoices_last_attempted_at_index');
            $table->dropColumn('last_attempted_at');
        });
    }
};
