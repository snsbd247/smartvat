<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A company may operate multiple business types (hybrid). Exactly one is primary.
        Schema::create('company_business_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // manufacturing | service | trading
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_business_types');
    }
};
