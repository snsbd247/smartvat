<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Letters of Credit / Telegraphic Transfers — import payment instruments.
        Schema::create('trade_finances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('tf_no', 40);
            $table->string('kind', 8); // lc | tt
            $table->string('party')->nullable();       // supplier/beneficiary
            $table->string('bank')->nullable();
            $table->string('ref_number')->nullable();   // LC no / TT ref
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 18, 4)->default(0);
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 16)->default('open'); // open | partial | settled | expired | cancelled
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'tf_no']);
        });

        // Shipment tracking — import and export consignments.
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('shipment_no', 40);
            $table->string('direction', 8); // import | export
            $table->string('transport_ref')->nullable(); // BL / AWB
            $table->string('carrier')->nullable();
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->string('status', 16)->default('booked'); // booked | in_transit | arrived | cleared | delivered
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'shipment_no']);
        });

        // Export documents — EXP, Certificate of Origin, etc.
        Schema::create('export_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('doc_no', 40);
            $table->string('doc_type', 24); // exp | coo | commercial_invoice | packing_list
            $table->string('party')->nullable();   // buyer
            $table->string('reference')->nullable();
            $table->date('date')->nullable();
            $table->string('status', 16)->default('draft'); // draft | issued | submitted
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'doc_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_documents');
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('trade_finances');
    }
};
