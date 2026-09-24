<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\EmailOpenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_email_id
 * @property int $recipient_id
 * @property CarbonImmutable $opened_at
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CampaignEmail $campaignEmail
 * @property-read Recipient $recipient
 */
#[Fillable(['campaign_email_id', 'recipient_id', 'opened_at', 'user_agent'])]
class EmailOpen extends Model
{
    /** @use HasFactory<EmailOpenFactory> */
    use HasFactory;

    /**
     * Get the email that was opened.
     *
     * @return BelongsTo<CampaignEmail, $this>
     */
    public function campaignEmail(): BelongsTo
    {
        return $this->belongsTo(CampaignEmail::class);
    }

    /**
     * Get the recipient that opened the email.
     *
     * @return BelongsTo<Recipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
        ];
    }
}
