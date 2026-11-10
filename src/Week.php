<?php

namespace ErnestDefoe\Kudos;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One week of kudos, under a rule (a start day and a timezone): it starts at
 * midnight on that day in that timezone and is stored by its first day
 * ("2026-11-09"). Which rule is in force is Weeks' business.
 */
final class Week
{
    private function __construct(public readonly CarbonImmutable $start)
    {
    }

    /** @param int $day 0 = Sunday … 6 = Saturday */
    public static function containing(CarbonInterface $moment, int $day, string $timezone): self
    {
        $local = CarbonImmutable::instance($moment)->setTimezone($timezone)->startOfDay();
        $back = ($local->dayOfWeek - $day + 7) % 7;

        return new self($local->subDays($back));
    }

    /** A week by its key (for showing its dates), or null for anything that isn't a date. */
    public static function fromKey(string $key, string $timezone): ?self
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $key)) {
            return null;
        }
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $key, $timezone);

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

    /** The week before, under the same rule (for seeding; live code takes stored keys instead). */
    public function previous(): self
    {
        return new self($this->start->subDays(7));
    }
}
