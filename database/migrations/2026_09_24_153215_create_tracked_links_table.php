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
        Schema::create('tracked_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_email_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('hash', 32)->unique();
            $table->timestamps();

            $table->index('campaign_email_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracked_links');
    }
};
