<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable mapping from a posting role (e.g. 'inventory', 'cogs') to a
        // GL account, per company. Keeps account numbers out of code.
        Schema::create('company_account_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key', 48); // inventory | grn_clearing | cogs | inventory_adjustment | ...
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_account_map');
    }
};
