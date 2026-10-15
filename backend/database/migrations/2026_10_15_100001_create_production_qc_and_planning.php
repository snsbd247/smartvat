<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->date('planned_start_date')->nullable()->after('date');
            $table->date('planned_end_date')->nullable()->after('planned_start_date');
        });

        Schema::create('production_qcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->string('qc_no', 40);
            $table->date('date');
            $table->decimal('inspected_qty', 18, 4);
            $table->decimal('passed_qty', 18, 4);
            $table->decimal('rejected_qty', 18, 4)->default(0);
            $table->string('inspector')->nullable();
            $table->string('result', 16)->default('pass'); // pass | partial | fail
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'qc_no']);
            $table->index(['company_id', 'production_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_qcs');
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn(['planned_start_date', 'planned_end_date']);
        });
    }
};
