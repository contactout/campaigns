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
            $table->timestamp('last_checked_at')->nullable()->after('sending_limit_refreshed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailer_connections', function (Blueprint $table) {
            $table->dropColumn('last_checked_at');
        });
    }
};
