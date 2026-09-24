<?php

namespace App\Models;

use App\Enums\EmailStatus;
use Database\Factories\CampaignEmailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $campaign_step_id
 * @property int $recipient_id
 * @property int|null $mailer_connection_id
 * @property string $thread_id
 * @property string $message_id
 * @property string $reply_to_id
 * @property string $tracker
 * @property EmailStatus $status
 * @property array<string, mixed>|null $data
 * @property int $reply_count
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $opened_at
 * @property Carbon|null $replied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read CampaignStep $step
 * @property-read Recipient $recipient
 * @property-read MailerConnection|null $mailerConnection
 */
#[Fillable(['campaign_id', 'campaign_step_id', 'recipient_id', 'mailer_connection_id', 'thread_id', 'message_id', 'reply_to_id', 'tracker', 'status', 'data', 'reply_count', 'scheduled_at', 'dispatched_at', 'delivered_at', 'opened_at', 'replied_at'])]
class CampaignEmail extends Model
{
    /** @use HasFactory<CampaignEmailFactory> */
    use HasFactory;

    /**
     * Get the campaign that owns the email.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the step that the email belongs to.
     *
     * @return BelongsTo<CampaignStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(CampaignStep::class, 'campaign_step_id');
    }

    /**
     * Get the recipient that the email is addressed to.
     *
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the mailer connection used to send the email.
     *
     * @return BelongsTo<MailerConnection, $this>
     */
    public function mailerConnection(): BelongsTo
    {
        return $this->belongsTo(MailerConnection::class);
    }

    /**
     * Scope a query to only include scheduled emails.
     *
     * @param  Builder<CampaignEmail>  $query
     * @return Builder<CampaignEmail>
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', EmailStatus::Scheduled);
    }

    /**
     * Scope a query to only include pending emails.
     *
     * @param  Builder<CampaignEmail>  $query
     * @return Builder<CampaignEmail>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EmailStatus::Pending);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
            'data' => 'array',
            'scheduled_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'opened_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }
}
