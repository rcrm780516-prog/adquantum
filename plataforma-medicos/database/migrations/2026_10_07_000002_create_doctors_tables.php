<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialty_id')->constrained();
            $table->foreignId('city_id')->constrained();
            $table->foreignId('plan_id')->nullable()->constrained();
            $table->timestamp('plan_expires_at')->nullable();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('title', 10)->default('Dr.'); // Dr. | Dra.
            $table->string('cedula_profesional', 20);
            $table->string('cedula_especialidad', 20)->nullable();
            $table->text('bio')->nullable();
            $table->json('services')->nullable();
            $table->json('insurances')->nullable();
            $table->unsignedInteger('consultation_price_mxn')->nullable();

            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('website')->nullable();

            // Ficha de Google
            $table->string('google_place_id')->nullable();
            $table->string('google_location_name')->nullable(); // accounts/{a}/locations/{l} en la API de GBP
            $table->unsignedTinyInteger('gbp_score')->nullable(); // 0-100, auditoría de la ficha

            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->index(['specialty_id', 'city_id', 'is_published']);
        });

        Schema::create('doctor_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = domingo ... 6 = sábado
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('doctors');
    }
};
