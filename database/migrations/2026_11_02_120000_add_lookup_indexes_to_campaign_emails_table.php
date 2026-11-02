<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds composite indexes for the hot campaign email lookups:
     * - due dispatch filters on status and ranges/orders on scheduled_at;
     * - reply and bounce matching look up a Message-ID within one connection;
     * - the bounce fallback walks one connection's sent emails, latest first.
     */
    public function up(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->index(['status', 'scheduled_at']);
            $table->index(['mailer_connection_id', 'message_id']);
            $table->index(['mailer_connection_id', 'dispatched_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * MySQL drops the index it created for the mailer connection foreign key
     * once these composite indexes can serve it, and then refuses to drop the
     * last of them. The foreign key is dropped and re-added around them so it
     * gets its own index back.
     */
    public function down(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropForeign(['mailer_connection_id']);
            $table->dropIndex(['status', 'scheduled_at']);
            $table->dropIndex(['mailer_connection_id', 'message_id']);
            $table->dropIndex(['mailer_connection_id', 'dispatched_at']);
            $table->foreign('mailer_connection_id')->references('id')->on('mailer_connections')->nullOnDelete();
        });
    }
};
