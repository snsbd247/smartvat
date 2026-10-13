<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('shipment_no', 40);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('currency', 3);               // foreign currency
            $table->decimal('exchange_rate', 18, 8);      // 1 FC = exchange_rate base currency
            $table->string('incoterm', 8)->nullable();
            $table->string('bl_no')->nullable();          // Bill of Lading / AWB
            $table->date('date');
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('draft'); // draft | received | cancelled
            $table->decimal('goods_value_bc', 18, 4)->default(0);
            $table->decimal('landed_value_bc', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'shipment_no']);
        });

        Schema::create('import_shipment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->unsignedBigInteger('batch_id')->default(0);
            $table->decimal('qty', 18, 4);
            $table->decimal('fc_unit_price', 18, 4);       // in foreign currency
            $table->decimal('weight', 18, 4)->default(0);  // optional allocation basis
            $table->decimal('volume', 18, 4)->default(0);
            $table->decimal('landed_unit_cost', 18, 4)->default(0); // filled on receive
            $table->timestamps();

            $table->index('import_shipment_id');
        });

        Schema::create('import_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24); // freight | insurance | customs_duty | port | cnf | other
            $table->string('currency', 3)->default('BDT');
            $table->decimal('fc_amount', 18, 4);
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->decimal('bc_amount', 18, 4);
            // Recoverable charges (e.g. import VAT) are NOT capitalized into inventory.
            $table->boolean('recoverable')->default(false);
            $table->string('allocation_basis', 16)->default('value'); // quantity|weight|volume|value
            $table->timestamps();

            $table->index('import_shipment_id');
        });

        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_shipment_line_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 18, 4); // base currency allocated to this line
            $table->timestamps();

            $table->index('import_shipment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');
        Schema::dropIfExists('import_charges');
        Schema::dropIfExists('import_shipment_lines');
        Schema::dropIfExists('import_shipments');
    }
};
