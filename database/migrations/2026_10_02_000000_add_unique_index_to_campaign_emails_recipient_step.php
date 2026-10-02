<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The tables that reference a campaign email and follow it when rows merge.
     *
     * @var array<int, string>
     */
    private const array TRACKING_TABLES = ['email_opens', 'link_clicks', 'tracked_links'];

    /**
     * Run the migrations.
     *
     * Two seed jobs running at once could insert a first-step row for the same
     * recipient twice. Existing duplicates are merged into the earliest row
     * first -- their opens, clicks and tracked links are moved across rather
     * than deleted -- so the unique index can be added without losing tracking
     * data.
     */
    public function up(): void
    {
        $duplicates = DB::table('campaign_emails')
            ->select('recipient_id', 'campaign_step_id')
            ->groupBy('recipient_id', 'campaign_step_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->mergeDuplicates((int) $duplicate->recipient_id, (int) $duplicate->campaign_step_id);
        }

        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->unique(['recipient_id', 'campaign_step_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaign_emails', function (Blueprint $table) {
            $table->dropUnique(['recipient_id', 'campaign_step_id']);
        });
    }

    /**
     * Keep the earliest email for a recipient and step, and fold the rest into it.
     */
    private function mergeDuplicates(int $recipientId, int $stepId): void
    {
        $ids = DB::table('campaign_emails')
            ->where('recipient_id', $recipientId)
            ->where('campaign_step_id', $stepId)
            ->orderBy('id')
            ->pluck('id');

        $keep = (int) $ids->first();

        /** @var array<int, int> $remove */
        $remove = $ids->slice(1)->map(intval(...))->values()->all();

        if ($remove === []) {
            return;
        }

        foreach (self::TRACKING_TABLES as $table) {
            DB::table($table)
                ->whereIn('campaign_email_id', $remove)
                ->update(['campaign_email_id' => $keep]);
        }

        DB::table('campaign_emails')->whereIn('id', $remove)->delete();
    }
};
