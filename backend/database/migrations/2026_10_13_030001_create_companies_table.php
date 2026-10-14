<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('country', 2)->default('BD');
            $table->string('base_currency', 3)->default('BDT');
            // Tax identifiers (e.g. BIN / VAT registration). Nullable — not every company is registered.
            $table->string('bin', 32)->nullable();
            $table->string('vat_reg_no', 64)->nullable();
            $table->string('status', 16)->default('active'); // active | inactive
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
