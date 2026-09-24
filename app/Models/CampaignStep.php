<?php

namespace App\Models;

use Database\Factories\CampaignStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $sequence
 * @property string $subject
 * @property string $body
 * @property int|null $day
 * @property string|null $time
 * @property bool $is_threaded
 * @property array<string, mixed>|null $setting
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Campaign $campaign
 * @property-read Collection<int, CampaignEmail> $emails
 */
#[Fillable(['campaign_id', 'sequence', 'subject', 'body', 'day', 'time', 'is_threaded', 'setting'])]
class CampaignStep extends Model
{
    /** @use HasFactory<CampaignStepFactory> */
    use HasFactory;

    /**
     * Get the campaign that owns the step.
     *
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Get the emails associated with the step.
     *
     * @return HasMany<CampaignEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(CampaignEmail::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_threaded' => 'boolean',
            'setting' => 'array',
        ];
    }
}
