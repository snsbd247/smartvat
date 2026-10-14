<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Restricts a user to specific factories within a company. Absence of any
        // row for a (user, company) means no factory restriction is applied yet;
        // enforcement is layered in per-module in later phases.
        Schema::create('user_factory_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('factory_id')->constrained('factories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'factory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_factory_access');
    }
};
