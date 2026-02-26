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
        Schema::create('staff_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained()->nullOnDelete();

            $table->text('message');
            $table->enum('channel', ['email', 'sms', 'in_app'])->default('in_app');

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['staff_id', 'read_at']);
            $table->index(['staff_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
    }
};
