<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->nullable()->constrained('factories')->nullOnDelete();
            $table->string('code', 32);
            $table->string('name');
            $table->string('type', 32)->default('general'); // general | raw | finished | ...
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'factory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
