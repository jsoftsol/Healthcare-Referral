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
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('hospital_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('staff')->nullOnDelete();

            $table->enum('urgency_level', ['routine', 'urgent', 'emergency']);
            $table->enum('status', [
                'pending', 'triaged', 'assigned', 'acknowledged',
                'in_progress', 'completed', 'cancelled', 'escalated',
            ])->default('pending');

            $table->json('icd10_codes');
            $table->text('clinical_notes');
            $table->string('department')->nullable();
            $table->string('ai_suggested_department')->nullable();
            $table->decimal('ai_confidence_score', 5, 4)->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->json('ai_input_payload')->nullable();
            $table->json('ai_output_payload')->nullable();

            $table->text('cancellation_reason')->nullable();

            $table->string('submitted_hash', 64)->unique()->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('urgency_level');
            $table->index('department');
            $table->index('created_at');
            $table->index(['hospital_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
