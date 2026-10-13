<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable, versioned, effective-dated VDS rules — verified before use.
        Schema::create('vds_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('service_category', 64);
            $table->decimal('rate', 8, 4); // percentage
            $table->date('effective_from')->nullable();
            $table->string('legal_ref')->nullable();
            $table->string('approval_status', 16)->default('draft');          // draft | approved
            $table->string('verification_status', 24)->default('requires_review'); // requires_review | verified
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'service_category']);
        });

        Schema::create('vds_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('deduction_no', 40);
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('service_category', 64);
            $table->decimal('base_amount', 18, 4);
            $table->decimal('rate', 8, 4);
            $table->decimal('vds_amount', 18, 4);
            $table->date('date');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 16)->default('deducted'); // deducted | deposited
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'deduction_no']);
        });

        Schema::create('vds_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vds_deduction_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_no', 40);
            $table->date('date');
            $table->timestamps();

            $table->unique(['company_id', 'certificate_no']);
        });

        Schema::create('vds_treasury_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('challan_no', 40);
            $table->date('date');
            $table->decimal('amount', 18, 4);
            $table->foreignId('paid_from_account_id')->constrained('chart_of_accounts');
            $table->string('reference')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'challan_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vds_treasury_deposits');
        Schema::dropIfExists('vds_certificates');
        Schema::dropIfExists('vds_deductions');
        Schema::dropIfExists('vds_rules');
    }
};
