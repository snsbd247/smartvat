<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current on-hand balance per (product, warehouse, batch, owner). batch_id and
        // owner_id use 0 to mean "none"/company so the unique index stays reliable
        // (NULLs are treated as distinct by MySQL unique indexes).
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->string('owner_type', 16)->default('company'); // company | customer
            $table->unsignedBigInteger('owner_id')->default(0);    // customer id when owner_type=customer
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('value', 18, 4)->default(0);           // total value (for weighted-average)
            $table->timestamps();

            $table->unique(
                ['company_id', 'product_id', 'warehouse_id', 'batch_id', 'owner_type', 'owner_id'],
                'stock_balance_unique',
            );
        });

        // Append-only ledger of every stock change. qty/value are signed (+ in, - out).
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->string('owner_type', 16)->default('company');
            $table->unsignedBigInteger('owner_id')->default(0);
            $table->decimal('qty', 18, 4);          // signed
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('value', 18, 4);        // signed
            $table->decimal('balance_qty', 18, 4);  // running balance after this movement
            $table->decimal('balance_value', 18, 4);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->timestamp('moved_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'product_id', 'warehouse_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};
