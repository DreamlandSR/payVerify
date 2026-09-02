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
        Schema::create('payment_extractions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('payment_proof_id')->constrained('payment_proofs')->cascadeOnDelete();
            $table->text('raw_ocr_text')->nullable();
            $table->decimal('extracted_amount', 15, 2)->nullable();
            $table->string('extracted_currency', 3)->default('IDR');
            $table->date('extracted_date')->nullable();
            $table->string('extracted_time', 20)->nullable();
            $table->string('extracted_provider', 100)->nullable();
            $table->string('extracted_ref_number', 100)->nullable();
            $table->string('extracted_merchant_name', 255)->nullable();
            $table->float('confidence_score')->nullable();
            $table->string('status')->default('PENDING');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_extractions');
    }
};
