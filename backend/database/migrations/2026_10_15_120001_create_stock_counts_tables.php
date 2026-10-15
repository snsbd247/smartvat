<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('count_no', 40);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->date('date');
            $table->string('status', 16)->default('posted');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'count_no']);
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('system_qty', 18, 4);
            $table->decimal('counted_qty', 18, 4);
            $table->decimal('variance', 18, 4);
            $table->timestamps();

            $table->index(['company_id', 'stock_count_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
    }
};
