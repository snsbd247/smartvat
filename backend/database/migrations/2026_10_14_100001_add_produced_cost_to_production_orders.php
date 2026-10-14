<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            // Finished-goods cost booked at receipt, retained after WIP is netted to zero.
            $table->decimal('produced_cost', 18, 4)->default(0)->after('wip_value');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('produced_cost');
        });
    }
};
