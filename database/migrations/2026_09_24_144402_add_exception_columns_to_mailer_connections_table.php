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
            $table->string('exception_type')->nullable()->after('status');
            $table->json('exception_data')->nullable()->after('exception_type');
            $table->timestamp('threw_at')->nullable()->after('exception_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mailer_connections', function (Blueprint $table) {
            $table->dropColumn(['exception_type', 'exception_data', 'threw_at']);
        });
    }
};
