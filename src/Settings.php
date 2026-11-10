<?php

namespace ErnestDefoe\Kudos;

use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/** Every knob, read with its default and clamped, so a bad saved value can't hand out unlimited kudos. */
final class Settings
{
    public const PREFIX = 'ernestdefoe-kudos.';

    public const NAV = self::PREFIX.'nav';
    public const BANNER = self::PREFIX.'banner';
    public const ALLOWANCE = self::PREFIX.'allowance';
    /** JSON {group id: kudos a week}; a member gets the most any of their groups (or the base) allows. */
    public const GROUP_ALLOWANCES = self::PREFIX.'group_allowances';
    /** 0 = Sunday … 6 = Saturday. */
    public const START_DAY = self::PREFIX.'start_day';
    public const TIMEZONE = self::PREFIX.'timezone';
    public const MODERATION = self::PREFIX.'moderation';
    /** Only kudos from accounts at least this many days old (when they gave) count toward the spotlight. */
    public const MIN_ACCOUNT_DAYS = self::PREFIX.'min_account_days';
    public const BADGE = self::PREFIX.'spotlight_badge';
    public const GROUP = self::PREFIX.'spotlight_group';
    /** Stamped by every kudos:spotlight, so the admin page can say when the scheduler last ran. */
    public const LAST_RUN = self::PREFIX.'last_run';

    public const VIEW = 'kudos.view';
    public const GIVE = 'kudos.give';
    public const MODERATE = 'kudos.moderate';

    public const NOTE_MIN = 3;
    public const NOTE_MAX = 280;

    /** The card designs a giver picks from (art in less/cards.less). */
    public const STYLES = ['sunrise', 'meadow', 'tide', 'confetti', 'starlight', 'letterpress'];

    public function __construct(private readonly SettingsRepositoryInterface $settings)
    {
    }

    public function baseAllowance(): int
    {
        $value = $this->settings->get(self::ALLOWANCE);

        return max(1, min(50, is_numeric($value) ? (int) $value : 3));
    }

    /** @return array<int, int> group id => kudos a week */
    public function groupAllowances(): array
    {
        $raw = json_decode((string) $this->settings->get(self::GROUP_ALLOWANCES), true);
        $out = [];
        foreach (is_array($raw) ? $raw : [] as $group => $count) {
            if (is_numeric($group) && is_numeric($count) && (int) $group !== Group::GUEST_ID) {
                $out[(int) $group] = max(1, min(50, (int) $count));
            }
        }

        return $out;
    }

    /** The member's kudos a week: the base, or more if one of their groups gets more. */
    public function allowanceFor(User $user): int
    {
        $best = $this->baseAllowance();
        $overrides = $this->groupAllowances();
        if ($overrides) {
            foreach ($user->groups as $group) {
                $best = max($best, $overrides[(int) $group->id] ?? 0);
            }
        }

        return $best;
    }

    public function startDay(): int
    {
        $value = $this->settings->get(self::START_DAY);

        return is_numeric($value) ? ((int) $value % 7 + 7) % 7 : 1;
    }

    /**
     * The week's timezone: Kudos's own setting when an admin picked one, else
     * the forum's (Flarum core has none, so the one the forum's other
     * extensions share: Festoon's, Fanfare's, Logo Manager's seasons), else
     * the app's (UTC unless config says otherwise). Read by key only, so none
     * of those extensions has to be installed.
     */
    public function timezone(): string
    {
        $candidates = [
            (string) $this->settings->get(self::TIMEZONE),
            (string) $this->settings->get('ernestdefoe-festoon.timezone'),
            (string) $this->settings->get('ernestdefoe-fanfare.timezone'),
            (string) (json_decode((string) $this->settings->get('ernestdefoe-logo-manager.config'), true)['seasons']['timezone'] ?? ''),
            (string) (resolve('config')['app.timezone'] ?? ''),
        ];
        foreach ($candidates as $zone) {
            if ($zone !== '' && in_array($zone, timezone_identifiers_list(), true)) {
                return $zone;
            }
        }

        return 'UTC';
    }

    public function minAccountDays(): int
    {
        $value = $this->settings->get(self::MIN_ACCOUNT_DAYS);

        return max(0, min(365, is_numeric($value) ? (int) $value : 7));
    }

    public function moderation(): bool
    {
        return (bool) $this->settings->get(self::MODERATION);
    }

    public function banner(): bool
    {
        return (bool) ($this->settings->get(self::BANNER) ?? true);
    }

    public function badgeId(): ?int
    {
        $value = $this->settings->get(self::BADGE);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** Never a built-in group: the spotlight must not make anyone an admin or a moderator by a slip of the select. */
    public function groupId(): ?int
    {
        $value = $this->settings->get(self::GROUP);
        $id = is_numeric($value) ? (int) $value : 0;

        return $id > Group::MODERATOR_ID ? $id : null;
    }

    public function stampRun(int $time): void
    {
        $this->settings->set(self::LAST_RUN, (string) $time);
    }

    public function lastRun(): ?int
    {
        $value = $this->settings->get(self::LAST_RUN);

        return is_numeric($value) ? (int) $value : null;
    }
}
