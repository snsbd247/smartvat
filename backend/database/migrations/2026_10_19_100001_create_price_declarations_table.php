<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Price / value declaration (Mushak মূল্য ঘোষণা) — the declared selling
        // value of a product, effective-dated. Trading companies declare this after
        // local/import purchase, before sales.
        Schema::create('price_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('decl_no', 40);
            $table->foreignId('product_id')->constrained('products');
            $table->decimal('declared_price', 18, 4);   // per unit
            $table->decimal('purchase_value', 18, 4)->nullable(); // landed/purchase unit value
            $table->date('effective_from');
            $table->string('status', 16)->default('draft'); // draft | approved
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'decl_no']);
            $table->index(['company_id', 'product_id', 'status', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_declarations');
    }
};
