<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('expense_no', 40);
            $table->date('date');
            $table->foreignId('expense_account_id')->constrained('chart_of_accounts');
            $table->foreignId('paid_from_account_id')->constrained('chart_of_accounts');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers')->nullOnDelete();
            $table->decimal('amount', 18, 4);
            $table->string('narration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'expense_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
