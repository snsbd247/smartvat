<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vat_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24);              // e.g. STD15, ZERO, EXEMPT
            $table->decimal('rate', 8, 4)->default(0); // percentage
            $table->date('effective_from')->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('vat_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32); // local_sale, export_sale, local_purchase, import_purchase, service_purchase
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('vat_tax_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32); // standard, zero_rated, exempt, ...
            $table->string('name');
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // Mushak 9.1 Note Numbers — verified statutory data, never invented in code.
        Schema::create('vat_return_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24); // the Note Number as per the official Mushak 9.1 form
            $table->string('title');
            $table->string('subform')->nullable();
            $table->date('effective_from')->nullable();
            $table->string('legal_ref')->nullable();
            $table->string('verification_status', 24)->default('requires_review'); // requires_review | verified
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // Classification tuple -> Note Number. Configurable, versioned, effective-dated,
        // approved + verified before it can be used. No note-to-subform logic in code.
        Schema::create('vat_note_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type_code', 32);
            $table->string('tax_treatment_code', 32);
            $table->string('vat_rate_code', 24)->nullable();   // null = any rate
            $table->string('category_code', 64)->nullable();   // null = any product/service category
            $table->foreignId('vat_return_note_id')->constrained('vat_return_notes');
            $table->string('subform')->nullable();
            $table->date('effective_from')->nullable();
            $table->string('rule_version', 24)->default('v1');
            $table->string('approval_status', 16)->default('draft');          // draft | approved
            $table->string('verification_status', 24)->default('requires_review'); // requires_review | verified
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'transaction_type_code', 'tax_treatment_code'], 'vat_note_map_co_txn_treat_idx');
        });

        // Result of classifying a source document line; freezes the rule version used.
        Schema::create('vat_transaction_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('line_ref')->nullable();
            $table->string('transaction_type_code', 32);
            $table->string('tax_treatment_code', 32);
            $table->string('vat_rate_code', 24)->nullable();
            $table->string('category_code', 64)->nullable();
            $table->decimal('taxable_amount', 18, 4)->default(0);
            $table->decimal('vat_amount', 18, 4)->default(0);
            $table->foreignId('vat_return_note_id')->nullable()->constrained('vat_return_notes');
            $table->string('subform')->nullable();
            $table->string('rule_version', 24)->nullable();
            $table->string('status', 24)->default('review_required'); // classified | review_required
            $table->date('date')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'vat_return_note_id'], 'vat_txn_class_co_note_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vat_transaction_classifications');
        Schema::dropIfExists('vat_note_mappings');
        Schema::dropIfExists('vat_return_notes');
        Schema::dropIfExists('vat_tax_treatments');
        Schema::dropIfExists('vat_transaction_types');
        Schema::dropIfExists('vat_rates');
    }
};
