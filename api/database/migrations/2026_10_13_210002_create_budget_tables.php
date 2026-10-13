<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('financial_year_id')->nullable()->constrained('financial_years')->nullOnDelete();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('amount', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
    }
};
