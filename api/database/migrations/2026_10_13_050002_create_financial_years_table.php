<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('open'); // open | closed
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('open'); // open | closed | locked
            $table->timestamps();

            $table->unique(['company_id', 'financial_year_id', 'name']);
            $table->index(['company_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('financial_years');
    }
};
