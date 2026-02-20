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
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('recurrence_type', ['monthly', 'weekly', 'bi-weekly', 'tri-weekly', 'manual', 'counted']);
            $table->integer('recurrence_interval')->default(1);
            $table->integer('total_count')->nullable(); // For 'counted' type
            $table->integer('generated_count')->default(0);
            $table->date('start_date');
            $table->date('next_invoice_date')->nullable();
            $table->enum('status', ['pending', 'active', 'completed', 'terminated'])->default('pending');
            $table->json('line_items');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('currency', 3)->default('IDR');
            $table->integer('due_date_offset')->nullable(); // Days after invoice date
            $table->text('notes')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_invoices');
    }
};
