<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('bom_no', 40);
            $table->foreignId('product_id')->constrained('products'); // finished good produced
            $table->integer('version')->default(1);
            $table->decimal('output_qty', 18, 4)->default(1); // lines are per this output qty
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'product_id', 'version']);
        });

        Schema::create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products');
            $table->decimal('qty', 18, 4); // input-output coefficient per output_qty
            $table->timestamps();
        });

        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 40);
            $table->foreignId('product_id')->constrained('products'); // output
            $table->foreignId('bom_id')->nullable()->constrained('boms')->nullOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('planned_qty', 18, 4);
            $table->decimal('produced_qty', 18, 4)->default(0);
            $table->decimal('scrap_qty', 18, 4)->default(0);
            $table->decimal('wip_value', 18, 4)->default(0); // accumulated issued cost not yet received
            $table->date('date');
            $table->string('status', 16)->default('draft'); // draft | released | completed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'order_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
        Schema::dropIfExists('bom_lines');
        Schema::dropIfExists('boms');
    }
};
