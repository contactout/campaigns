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
        Schema::table('mailer_connections', function (Blueprint $table) {
            $table->unsignedInteger('min_seconds_between_sends')->nullable()->after('sending_limit');
            $table->timestamp('last_sent_at')->nullable()->after('sent_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailer_connections', function (Blueprint $table) {
            $table->dropColumn(['min_seconds_between_sends', 'last_sent_at']);
        });
    }
};
