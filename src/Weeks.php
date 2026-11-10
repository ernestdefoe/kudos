<?php

namespace ErnestDefoe\Kudos;

use Carbon\CarbonImmutable;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Which week it is.
 *
 * 🚨 A change to the start day or the timezone (Kudos's own, or the forum's
 * it follows) never applies mid-week: that would start a new week key on a
 * Wednesday, so allowances would reset, the same person could be thanked
 * twice, and Monday–Tuesday's cards would fall into a week of their own.
 * The rule in force is stored (`week_rule`: day + timezone) and a changed
 * rule waits in `next` until the current week ends under the old one, then
 * takes over. The week that contains that moment under the new rule may
 * start a day or two "early", but its key is always later than the old
 * week's, so keys stay in order.
 *
 * Callers that need "the week before" never compute it from the rule: they
 * take the latest week key actually stored (see Spotlights, the wall).
 */
class Weeks
{
    public const STATE = Settings::PREFIX.'week_rule';

    /** @var array{day: int, tz: string, next?: array{day: int, tz: string, from: int}}|null */
    private ?array $state = null;

    public function __construct(
        private readonly Settings $settings,
        private readonly SettingsRepositoryInterface $repository,
    ) {
    }

    public function current(): Week
    {
        $now = CarbonImmutable::now();
        $state = $this->state();
        $wanted = ['day' => $this->settings->startDay(), 'tz' => $this->settings->timezone()];
        $changed = false;

        // A pending rule whose moment has come takes over.
        if (isset($state['next']) && $now->getTimestamp() >= $state['next']['from']) {
            $state = ['day' => $state['next']['day'], 'tz' => $state['next']['tz']];
            $changed = true;
        }

        $week = Week::containing($now, $state['day'], $state['tz']);

        if ($wanted['day'] === $state['day'] && $wanted['tz'] === $state['tz']) {
            // Changed back before it took effect: nothing pending.
            if (isset($state['next'])) {
                unset($state['next']);
                $changed = true;
            }
        } elseif (! isset($state['next']) || $state['next']['day'] !== $wanted['day'] || $state['next']['tz'] !== $wanted['tz']) {
            $state['next'] = $wanted + ['from' => $week->end()->getTimestamp()];
            $changed = true;
        }

        if ($changed) {
            $this->save($state);
        }

        return $week;
    }

    /** The rule weeks are shown in (the one in force). */
    public function timezone(): string
    {
        return $this->state()['tz'];
    }

    /** When a changed rule takes over, if one is waiting (for the admin page). */
    public function pendingFrom(): ?int
    {
        $this->current();

        return $this->state()['next']['from'] ?? null;
    }

    /** @return array{day: int, tz: string, next?: array{day: int, tz: string, from: int}} */
    private function state(): array
    {
        if ($this->state !== null) {
            return $this->state;
        }
        $raw = json_decode((string) $this->repository->get(self::STATE), true);
        if (is_array($raw) && isset($raw['day'], $raw['tz']) && in_array($raw['tz'], timezone_identifiers_list(), true)) {
            $state = ['day' => ((int) $raw['day'] % 7 + 7) % 7, 'tz' => (string) $raw['tz']];
            if (isset($raw['next']['day'], $raw['next']['tz'], $raw['next']['from']) && in_array($raw['next']['tz'], timezone_identifiers_list(), true)) {
                $state['next'] = ['day' => ((int) $raw['next']['day'] % 7 + 7) % 7, 'tz' => (string) $raw['next']['tz'], 'from' => (int) $raw['next']['from']];
            }

            return $this->state = $state;
        }

        // First use: whatever the settings say now is the rule.
        $state = ['day' => $this->settings->startDay(), 'tz' => $this->settings->timezone()];
        $this->save($state);

        return $state;
    }

    /** @param array{day: int, tz: string, next?: array{day: int, tz: string, from: int}} $state */
    private function save(array $state): void
    {
        $this->state = $state;
        $this->repository->set(self::STATE, (string) json_encode($state));
    }
}
