<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->boolean('reconciled')->default(false)->after('narration');
            $table->timestamp('reconciled_at')->nullable()->after('reconciled');
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->date('statement_date');
            $table->decimal('statement_balance', 18, 4);
            $table->decimal('book_balance', 18, 4);
            $table->decimal('reconciled_balance', 18, 4);
            $table->decimal('difference', 18, 4);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_reconciliations');
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropColumn(['reconciled', 'reconciled_at']);
        });
    }
};
