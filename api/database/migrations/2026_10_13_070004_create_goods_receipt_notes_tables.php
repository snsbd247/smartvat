<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('grn_no', 40);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('date');
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('posted');
            $table->decimal('total_value', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'grn_no']);
        });

        Schema::create('grn_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_note_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_value', 18, 4);
            $table->timestamps();

            $table->index('goods_receipt_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_lines');
        Schema::dropIfExists('goods_receipt_notes');
    }
};
