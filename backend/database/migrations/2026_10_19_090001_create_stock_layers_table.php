<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cost layers for FIFO / LIFO valuation. Each inbound movement creates a
        // layer; outbound movements consume layers oldest-first (FIFO) or
        // newest-first (LIFO). Weighted-average products do not use layers.
        Schema::create('stock_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->string('owner_type', 16)->default('company');
            $table->unsignedBigInteger('owner_id')->default(0);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('qty_received', 18, 4);
            $table->decimal('qty_remaining', 18, 4);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'warehouse_id', 'owner_type', 'owner_id', 'qty_remaining'], 'stock_layers_consume_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layers');
    }
};
