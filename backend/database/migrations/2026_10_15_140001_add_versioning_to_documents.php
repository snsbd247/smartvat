<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('category');
            // Groups all versions of the same logical document together.
            $table->string('group_key', 40)->nullable()->after('version');
            $table->index(['company_id', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'group_key']);
            $table->dropColumn(['version', 'group_key']);
        });
    }
};
