<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // System-wide (not tenant-scoped): a backup covers the whole database.
        Schema::create('backup_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('remote_ref')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status', 16)->default('running'); // running | success | failed
            $table->boolean('uploaded')->default(false);
            $table->boolean('verified')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'finished_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_jobs');
    }
};
