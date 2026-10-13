<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional party (supplier/customer) dimension on a ledger line, so AP/AR
        // balances can be attributed per supplier/customer without a separate ledger.
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->string('party_type', 24)->nullable()->after('cost_center_id'); // supplier | customer
            $table->unsignedBigInteger('party_id')->nullable()->after('party_type');
            $table->index(['party_type', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::table('journal_lines', function (Blueprint $table) {
            $table->dropIndex(['party_type', 'party_id']);
            $table->dropColumn(['party_type', 'party_id']);
        });
    }
};
