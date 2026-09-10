<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A generated PDF's history — one row per Quotation/Proforma
        // Invoice/Invoice/Receipt actually produced for a job. Replaces the
        // old app's JSONB "documents" living inside jobs.attachments with a
        // proper table so it can be queried by doc_type/doc_number without
        // array scanning. "Current vs superseded" (regenerating the same
        // doc_type for a job) is derived at read time from doc_type +
        // generated_at ordering, not stored — same as the old app.
        Schema::create('job_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained()->cascadeOnDelete();
            $table->string('doc_type'); // quotation, proforma, invoice, receipt
            $table->string('doc_number');
            $table->string('storage_path');
            $table->string('filename');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['job_id', 'doc_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_documents');
    }
};
