<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('email');
            $table->string('mobile', 40)->nullable()->after('username');
            // NID is sensitive: the plaintext is encrypted at rest (nid_encrypted);
            // nid_index is a keyed hash (blind index) for exact-match lookup without
            // decrypting. Never store the raw NID.
            $table->text('nid_encrypted')->nullable()->after('mobile');
            $table->string('nid_index', 64)->nullable()->index()->after('nid_encrypted');
            $table->string('photo_path')->nullable()->after('nid_index');
            $table->string('address')->nullable()->after('photo_path');
            $table->string('employee_id', 64)->nullable()->after('address');
            $table->string('designation')->nullable()->after('employee_id');
            $table->string('status', 16)->default('active')->after('designation'); // active | inactive
            $table->foreignId('created_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable()->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn([
                'username', 'mobile', 'nid_encrypted', 'nid_index', 'photo_path',
                'address', 'employee_id', 'designation', 'status', 'last_login_at',
            ]);
        });
    }
};
