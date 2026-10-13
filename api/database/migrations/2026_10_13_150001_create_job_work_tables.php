<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manufacturing_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('contract_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->decimal('processing_rate', 18, 4)->default(0); // conversion fee per unit
            $table->string('scrap_ownership', 16)->default('customer'); // customer | company
            $table->string('terms')->nullable();
            $table->date('date');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'contract_no']);
        });

        Schema::create('job_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 40);
            $table->foreignId('manufacturing_contract_id')->nullable()->constrained('manufacturing_contracts')->nullOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('product_id')->nullable()->constrained('products'); // output
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('planned_qty', 18, 4)->default(0);
            $table->string('status', 16)->default('open'); // open | completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'order_no']);
        });

        // Processing (conversion) invoice — a SERVICE charge, never a sale of goods.
        Schema::create('processing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('job_work_order_id')->nullable()->constrained('job_work_orders')->nullOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('rate', 18, 4);
            $table->decimal('amount', 18, 4);
            $table->date('date');
            $table->string('status', 16)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'invoice_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processing_invoices');
        Schema::dropIfExists('job_work_orders');
        Schema::dropIfExists('manufacturing_contracts');
    }
};
