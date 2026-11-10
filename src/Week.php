<?php

namespace ErnestDefoe\Kudos;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Weeks in the forum's own calendar: they start on the admin's day at
 * midnight in the admin's timezone. A week is stored by its first day
 * ("2026-11-09"), so changing the settings never moves kudos already given.
 */
final class Week
{
    private function __construct(public readonly CarbonImmutable $start)
    {
    }

    public static function containing(CarbonInterface $moment, Settings $settings): self
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($settings->timezone())->startOfDay();
        $back = ($local->dayOfWeek - $settings->startDay() + 7) % 7;

        return new self($local->subDays($back));
    }

    public static function current(Settings $settings): self
    {
        return self::containing(CarbonImmutable::now(), $settings);
    }

    /** A week by its key, or null for anything that isn't a date. */
    public static function fromKey(string $key, Settings $settings): ?self
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
            return null;
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $key, $settings->timezone());

        return $day && $day->format('Y-m-d') === $key ? new self($day) : null;
    }

    public function key(): string
    {
        return $this->start->format('Y-m-d');
    }

    public function end(): CarbonImmutable
    {
        return $this->start->addDays(7);
    }

    public function previous(): self
    {
        return new self($this->start->subDays(7));
    }
}
