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
        Schema::create('mailer_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('mailer_type');
            $table->text('smtp_setting')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamp('rate_limit_expired_at')->nullable();
            $table->unsignedInteger('sending_limit')->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('sending_limit_refreshed_at')->nullable();
            $table->timestamps();

            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mailer_connections');
    }
};
