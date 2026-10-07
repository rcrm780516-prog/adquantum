<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Solo datos de contacto: no se guarda información clínica (LFPDPPP).
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('patient_name');
            $table->string('patient_phone', 20);
            $table->string('patient_email')->nullable();
            $table->string('status', 20)->default('pending'); // pending | confirmed | completed | cancelled | no_show
            $table->string('review_token', 64)->unique();
            $table->timestamp('privacy_accepted_at');
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamps();

            $table->index(['doctor_id', 'starts_at']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('patient_name');
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->boolean('is_verified')->default(false); // viene de una cita real
            $table->string('status', 20)->default('published'); // published | reported | removed (solo por moderación)
            $table->text('doctor_reply')->nullable();
            $table->timestamp('google_invite_clicked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('appointments');
    }
};
