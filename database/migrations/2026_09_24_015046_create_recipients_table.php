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
        Schema::create('recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('timezone')->nullable();
            $table->string('status')->default('Active');
            $table->string('source')->nullable();
            $table->json('placeholders')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('next_scheduled_at')->nullable();
            $table->timestamp('last_responded_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipients');
    }
};
