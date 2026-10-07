<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctors', function (Blueprint $table) {
            // ID del Google Calendar que el médico compartió con la cuenta de servicio (normalmente su Gmail).
            $table->string('google_calendar_id')->nullable()->after('google_location_name');
            $table->timestamp('google_calendar_checked_at')->nullable()->after('google_calendar_id');
            $table->string('google_calendar_error')->nullable()->after('google_calendar_checked_at');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('google_event_id')->nullable()->after('review_requested_at');
            $table->string('google_sync_error')->nullable()->after('google_event_id');
        });

        // El médico no edita su perfil: pide cambios y el equipo de Virtuoso los aplica.
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('pending'); // pending | done
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
        Schema::table('appointments', fn (Blueprint $table) => $table->dropColumn(['google_event_id', 'google_sync_error']));
        Schema::table('doctors', fn (Blueprint $table) => $table->dropColumn(['google_calendar_id', 'google_calendar_checked_at', 'google_calendar_error']));
    }
};
