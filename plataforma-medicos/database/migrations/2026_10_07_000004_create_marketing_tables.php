<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // copy | advice | gbp_description | review_reply
            $table->text('input');
            $table->text('output');
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->index(['doctor_id', 'created_at']);
        });

        Schema::create('creatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('template');
            $table->string('format', 20); // post | story | banner
            $table->json('data');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        // Embudo hacia Virtuoso: cada clic de "quiero más" queda registrado.
        Schema::create('upgrade_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->string('plan_interest', 20);
            $table->string('source', 40); // panel | ai_limit | advisor | creatives_limit
            $table->text('message')->nullable();
            $table->string('status', 20)->default('new'); // new | contacted | won | lost
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('provider', 20)->default('manual'); // manual | mercadopago | stripe
            $table->string('provider_reference')->nullable();
            $table->unsignedInteger('amount_mxn');
            $table->string('status', 20)->default('pending'); // pending | active | expired | cancelled
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('upgrade_leads');
        Schema::dropIfExists('creatives');
        Schema::dropIfExists('ai_generations');
    }
};
