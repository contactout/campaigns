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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('Draft');
            $table->string('timezone')->default('UTC');
            $table->foreignId('mailer_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->string('interrupted_reason')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
