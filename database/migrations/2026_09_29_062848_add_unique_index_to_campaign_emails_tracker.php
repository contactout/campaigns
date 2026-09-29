<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Existing rows were created with an empty tracker, so each one is given a
     * random value before the unique index is added.
     */
    public function up(): void
    {
        DB::table('campaign_emails')
            ->where('tracker', '')
            ->orderBy('id')
            ->select('id')
            ->chunkById(500, function ($emails): void {
                foreach ($emails as $email) {
                    DB::table('campaign_emails')
                        ->where('id', $email->id)
                        ->update(['tracker' => Str::random(32)]);
                }
            });

        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->unique('tracker');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropUnique(['tracker']);
        });
    }
};
