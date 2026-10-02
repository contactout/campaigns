<?php

namespace App\Data;

/**
 * The scheduling and tracking options stored in a campaign's `settings` column.
 *
 * `sendingDays` holds ISO weekday numbers (1 = Monday, 7 = Sunday). The hour
 * window is end-exclusive and follows mail merge's convention: 0/0 means no
 * hour restriction at all, and 23/0 means only 23:00 is allowed.
 */
class CampaignSettings
{
    /**
     * Every ISO weekday, in order.
     *
     * @var array<int, int>
     */
    public const array ALL_DAYS = [1, 2, 3, 4, 5, 6, 7];

    /**
     * @param  array<int, int>  $sendingDays
     */
    public function __construct(
        public readonly array $sendingDays = self::ALL_DAYS,
        public readonly int $sendingHourFrom = 0,
        public readonly int $sendingHourTo = 0,
        public readonly bool $openTracking = true,
        public readonly bool $linkTracking = true,
    ) {}

    /**
     * Read the settings, falling back to the defaults for anything missing.
     *
     * Values are normalised rather than trusted: the column is JSON, so it can
     * be edited outside the app.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public static function fromArray(?array $settings): self
    {
        $settings ??= [];

        $days = array_values(array_unique(array_filter(
            array_map(intval(...), is_array($settings['sending_days'] ?? null) ? $settings['sending_days'] : self::ALL_DAYS),
            static fn (int $day): bool => $day >= 1 && $day <= 7,
        )));

        $from = self::hour($settings['sending_hour_from'] ?? 0);
        $to = self::hour($settings['sending_hour_to'] ?? 0);

        if (! self::isValidHourRange($from, $to)) {
            $from = 0;
            $to = 0;
        }

        return new self(
            sendingDays: $days === [] ? self::ALL_DAYS : $days,
            sendingHourFrom: $from,
            sendingHourTo: $to,
            openTracking: (bool) ($settings['open_tracking'] ?? true),
            linkTracking: (bool) ($settings['link_tracking'] ?? true),
        );
    }

    /**
     * Get the settings in the shape stored on the campaign.
     *
     * @return array{sending_days: array<int, int>, sending_hour_from: int, sending_hour_to: int, open_tracking: bool, link_tracking: bool}
     */
    public function toArray(): array
    {
        return [
            'sending_days' => $this->sendingDays,
            'sending_hour_from' => $this->sendingHourFrom,
            'sending_hour_to' => $this->sendingHourTo,
            'open_tracking' => $this->openTracking,
            'link_tracking' => $this->linkTracking,
        ];
    }

    /**
     * Determine whether the campaign may send at any hour of the day.
     */
    public function isUnrestrictedByHour(): bool
    {
        return $this->sendingHourFrom === 0 && $this->sendingHourTo === 0;
    }

    /**
     * Determine whether the campaign may send on the given ISO weekday.
     */
    public function allowsWeekday(int $isoWeekday): bool
    {
        return in_array($isoWeekday, $this->sendingDays, true);
    }

    /**
     * Determine whether the campaign may send during the given local hour.
     */
    public function allowsHour(int $hour): bool
    {
        if ($this->isUnrestrictedByHour()) {
            return true;
        }

        if ($this->sendingHourFrom === 23 && $this->sendingHourTo === 0) {
            return $hour === 23;
        }

        return $hour >= $this->sendingHourFrom && $hour < $this->sendingHourTo;
    }

    /**
     * Determine whether an hour range describes a window that can ever be open.
     */
    public static function isValidHourRange(int $from, int $to): bool
    {
        return ($from === 0 && $to === 0)
            || ($from === 23 && $to === 0)
            || ($from >= 0 && $from <= 23 && $to >= 0 && $to <= 23 && $from < $to);
    }

    /**
     * Clamp a stored hour into the range the columns accept.
     */
    private static function hour(mixed $value): int
    {
        return max(0, min(23, (int) $value));
    }
}
