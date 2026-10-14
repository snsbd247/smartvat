<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('inv_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_order_id')->nullable()->constrained('sales_orders')->nullOnDelete();
            $table->string('kind', 8)->default('local'); // local | export
            $table->string('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->date('date');
            $table->decimal('subtotal_fc', 18, 4)->default(0); // foreign currency (export)
            $table->decimal('subtotal', 18, 4)->default(0);    // base currency
            $table->decimal('total', 18, 4)->default(0);       // base currency
            $table->string('narration')->nullable();
            $table->string('status', 16)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'inv_no']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('description')->nullable();
            $table->decimal('qty', 18, 4)->default(1);
            $table->decimal('unit_price', 18, 4);   // in invoice currency
            $table->decimal('line_total', 18, 4);   // in invoice currency
            $table->timestamps();
        });

        // Delivery challan — a separate document from the invoice; drives stock-out.
        Schema::create('delivery_challans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('challan_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('date');
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('posted');
            $table->decimal('cogs_value', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'challan_no']);
        });

        Schema::create('delivery_challan_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_challan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->decimal('qty', 18, 4);
            $table->timestamps();
        });

        Schema::create('customer_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->date('date');
            $table->string('currency', 3)->default('BDT');
            $table->decimal('fc_amount', 18, 4)->default(0);        // export: foreign currency received
            $table->decimal('realization_rate', 18, 8)->default(1); // rate at realization
            $table->decimal('invoice_rate', 18, 8)->default(1);     // rate AR was booked at
            $table->decimal('amount', 18, 4);                       // base currency received
            $table->foreignId('received_in_account_id')->constrained('chart_of_accounts');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_no']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('return_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('date');
            $table->decimal('amount', 18, 4)->default(0);     // credit note value (base currency)
            $table->decimal('cogs_value', 18, 4)->default(0); // goods returned at cost
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'return_no']);
        });

        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_lines');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('customer_receipts');
        Schema::dropIfExists('delivery_challan_lines');
        Schema::dropIfExists('delivery_challans');
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
