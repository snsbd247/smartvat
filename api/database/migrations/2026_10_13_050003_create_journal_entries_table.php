<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('entry_no', 40);
            $table->date('date');
            $table->foreignId('period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            // Idempotency key: a source transaction + purpose maps to at most one entry.
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('purpose', 40)->default('primary');
            $table->string('narration')->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->string('status', 16)->default('posted'); // draft | posted | reversed
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'entry_no']);
            // Nullable source columns => manual entries (null source) never collide;
            // sourced entries are unique per (source, purpose) for idempotency.
            $table->unique(['company_id', 'source_type', 'source_id', 'purpose'], 'je_source_unique');
            $table->index(['company_id', 'date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->decimal('debit', 18, 4)->default(0);
            $table->decimal('credit', 18, 4)->default(0);
            $table->string('currency', 3)->default('BDT');
            $table->decimal('fx_rate', 18, 8)->default(1);
            $table->string('narration')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'account_id']);
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
