<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header for an inventory document (receipt/issue/transfer/adjustment). The
        // stock side is recorded in stock_movements and the GL side in journal_entries,
        // both linked back via source_type='inventory_transaction' + source_id.
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);  // receipt | issue | transfer | adjustment
            $table->string('txn_no', 40);
            $table->date('date');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('to_warehouse_id')->nullable()->constrained('warehouses'); // transfers
            $table->string('reference')->nullable();
            $table->string('narration')->nullable();
            $table->string('status', 16)->default('posted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'txn_no']);
            $table->index(['company_id', 'type', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
