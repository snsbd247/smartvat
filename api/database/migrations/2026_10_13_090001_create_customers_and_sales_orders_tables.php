<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('country', 2)->default('BD');
            $table->string('bin', 32)->nullable();
            $table->string('vat_reg_no', 64)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('so_no', 40);
            $table->foreignId('customer_id')->constrained('customers');
            $table->string('kind', 8)->default('local'); // local | export
            $table->string('currency', 3)->default('BDT');
            $table->decimal('exchange_rate', 18, 8)->default(1);
            $table->date('date');
            $table->string('status', 16)->default('draft'); // draft | approved | closed | cancelled
            $table->string('reference')->nullable();
            $table->decimal('total', 18, 4)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'so_no']);
        });

        Schema::create('sales_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products');
            $table->string('description')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('line_total', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_lines');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('customers');
    }
};
