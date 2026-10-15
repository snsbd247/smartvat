<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->string('location')->nullable()->after('status');
            $table->string('custodian')->nullable()->after('location');
        });

        // Location/custodian transfers — operational, no GL impact.
        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->date('date');
            $table->string('from_location')->nullable();
            $table->string('to_location')->nullable();
            $table->string('from_custodian')->nullable();
            $table->string('to_custodian')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'fixed_asset_id']);
        });

        // Maintenance events — expensed to P&L.
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->string('maint_no', 40);
            $table->date('date');
            $table->decimal('amount', 18, 4);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'maint_no']);
            $table->index(['company_id', 'fixed_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
        Schema::dropIfExists('asset_movements');
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn(['location', 'custodian']);
        });
    }
};
