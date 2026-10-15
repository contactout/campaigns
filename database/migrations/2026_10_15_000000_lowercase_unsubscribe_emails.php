<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lowercase legacy unsubscribe addresses so suppression lookups can match
     * them exactly.
     *
     * When lowercasing would collide with another entry of the same team, the
     * older entry is kept and the duplicate removed.
     */
    public function up(): void
    {
        DB::table('unsubscribes')
            ->select(['id', 'team_id', 'email'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $email = mb_strtolower(trim($row->email));

                    if ($email === $row->email) {
                        continue;
                    }

                    $keeper = DB::table('unsubscribes')
                        ->where('team_id', $row->team_id)
                        ->where('id', '!=', $row->id)
                        ->where('email', $email)
                        ->orderBy('id')
                        ->value('id');

                    if ($keeper !== null && $keeper < $row->id) {
                        DB::table('unsubscribes')->where('id', $row->id)->delete();

                        continue;
                    }

                    if ($keeper !== null) {
                        DB::table('unsubscribes')->where('id', $keeper)->delete();
                    }

                    DB::table('unsubscribes')->where('id', $row->id)->update(['email' => $email]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Lowercased addresses cannot be restored to their original case.
    }
};
