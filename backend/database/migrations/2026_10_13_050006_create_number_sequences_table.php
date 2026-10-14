<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-company atomic document counters (journal entries, invoices, etc.).
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key', 64);           // e.g. 'journal_entry'
            $table->string('prefix', 16)->default('');
            $table->unsignedBigInteger('next_no')->default(1);
            $table->unsignedTinyInteger('padding')->default(5);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_sequences');
    }
};
