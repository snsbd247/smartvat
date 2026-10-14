<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimal foundational products so the stock engine has something to move.
        // Extended with categories/attributes/alt-UoM in the MasterData phase.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->string('type', 24)->default('raw'); // raw|finished|packaging|consumable|spare|service
            $table->string('base_uom', 16)->default('PCS');
            $table->string('costing_method', 24)->default('weighted_average'); // weighted_average | fifo
            $table->boolean('track_batches')->default(false);
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
