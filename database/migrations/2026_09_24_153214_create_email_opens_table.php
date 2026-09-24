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
        Schema::create('email_opens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('campaign_email_id');
            $table->index('recipient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_opens');
    }
};
