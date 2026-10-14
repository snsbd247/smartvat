<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('method', 24)->default('straight_line');
            $table->integer('useful_life_months')->default(60);
            // GL accounts for this category's postings.
            $table->foreignId('asset_account_id')->constrained('chart_of_accounts');
            $table->foreignId('depreciation_expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('accumulated_account_id')->constrained('chart_of_accounts'); // contra-asset
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });

        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('asset_no', 40);
            $table->foreignId('asset_category_id')->constrained('asset_categories');
            $table->string('name');
            $table->date('acquisition_date');
            $table->decimal('cost', 18, 4);
            $table->decimal('salvage_value', 18, 4)->default(0);
            $table->integer('useful_life_months');
            $table->string('method', 24)->default('straight_line');
            $table->decimal('accumulated_depreciation', 18, 4)->default(0);
            $table->decimal('book_value', 18, 4)->default(0);
            $table->string('status', 16)->default('active'); // active | disposed
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'asset_no']);
        });

        Schema::create('depreciation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7); // YYYY-MM
            $table->decimal('amount', 18, 4);
            $table->date('date');
            $table->timestamps();

            // One depreciation entry per asset per month (idempotency).
            $table->unique(['fixed_asset_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('depreciation_entries');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('asset_categories');
    }
};
