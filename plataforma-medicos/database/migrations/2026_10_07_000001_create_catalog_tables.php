<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('doctor')->after('email'); // doctor | admin
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('schema_type')->default('Physician'); // tipo Schema.org (medicalSpecialty)
            $table->text('description')->nullable();
            $table->json('ad_keywords')->nullable(); // palabras clave para plantillas y copys
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('state');
            $table->timestamps();
        });

        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // basico | pro | virtuoso
            $table->string('name');
            $table->unsignedInteger('price_mxn');
            $table->string('billing_interval', 10); // year | month | custom
            $table->unsignedInteger('ai_monthly_limit')->default(20);
            $table->unsignedInteger('creatives_monthly_limit')->default(5);
            $table->json('features');
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('specialties');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
