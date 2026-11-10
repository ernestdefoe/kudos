<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use ErnestDefoe\Kudos\Notification\SpotlightBlueprint;
use Flarum\Extension\ExtensionManager;
use Flarum\Group\Group;
use Flarum\Notification\NotificationSyncer;
use Flarum\User\User;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * The weekly spotlight: the most-thanked member of the week that just ended.
 *
 * The week judged is the latest week key actually stored before the current
 * one (never one computed from the rule, which may have changed in between),
 * and only when no spotlight is at or after it: a scheduler that was down
 * for a month judges one week, not four. With the moderation queue on it
 * waits a grace period into the new week, so Sunday-night kudos approved on
 * Monday still count.
 *
 * Who counts: approved kudos from givers whose accounts were at least
 * `min_account_days` old when they gave (fresh accounts can't farm it). Who
 * wins: the most kudos, ties to whoever got there first; a winner who is
 * unconfirmed or suspended is skipped for the next.
 *
 * Idempotent: the week is a unique key and only the run whose insert lands
 * hands anything out; rewards are claimed (`rewarded_at`) and unclaimed on
 * failure, so a later run finishes them; the group joined is written to the
 * row the moment it's joined, so it can always be taken back. The group is
 * worn for one week: expireGroups() takes it back from every spotlight made
 * before the current week began.
 */
class Spotlights
{
    public const CACHE_KEY = 'ernestdefoe-kudos.spotlight';
    public const QUEUE_GRACE_HOURS = 24;

    public function __construct(
        private readonly Settings $settings,
        private readonly Weeks $weeks,
        private readonly Eligibility $eligibility,
        private readonly ConnectionInterface $db,
        private readonly NotificationSyncer $notifications,
        private readonly ExtensionManager $extensions,
        private readonly Cache $cache,
        private readonly LoggerInterface $log,
    ) {
    }

    /** Judge the week that just ended, if nobody has yet. Returns the spotlight when this run set it. */
    public function run(): ?Spotlight
    {
        $current = $this->weeks->current();
        $made = null;

        $grace = $this->settings->moderation() ? self::QUEUE_GRACE_HOURS : 0;
        $last = Kudos::query()->where('week', '<', $current->key())->max('week');
        $last = $last ? substr((string) $last, 0, 10) : null;

        if ($last && Carbon::now()->gte($current->start->addHours($grace)) && ! Spotlight::query()->where('week', '>=', $last)->exists()) {
            $winner = $this->winner($last);
            if ($winner) {
                $inserted = $this->db->table('kudos_spotlights')->insertOrIgnore([
                    'week' => $last,
                    'user_id' => $winner[0],
                    'count' => $winner[1],
                    'created_at' => Carbon::now(),
                ]);
                if ($inserted) {
                    $made = Spotlight::query()->where('week', $last)->first();
                    $this->forget();
                }
            }
        }

        if ($last) {
            $due = Spotlight::query()->where('week', $last)->whereNull('rewarded_at')->first();
            if ($due) {
                $this->reward($due);
            }
        }
        $this->expireGroups(Carbon::instance($current->start->utc()));

        return $made;
    }

    /**
     * The week's winner: [user id, kudos counted], or null.
     *
     * @return array{0: int, 1: int}|null
     */
    public function winner(string $week): ?array
    {
        $minDays = $this->settings->minAccountDays();
        $kudos = Kudos::query()->where('week', $week)->where('status', Kudos::APPROVED)->whereNotNull('giver_id')
            ->with('giver')->get(['id', 'giver_id', 'receiver_id', 'created_at']);

        $tally = [];
        foreach ($kudos as $k) {
            if (! $k->giver) {
                continue;
            }
            // Too new when they gave. (No join date at all = an account imported from elsewhere: it counts.)
            $joined = $k->giver->joined_at;
            if ($joined && $joined->gt($k->created_at->copy()->subDays($minDays))) {
                continue;
            }
            $id = (int) $k->receiver_id;
            $tally[$id] ??= ['id' => $id, 'count' => 0, 'reached' => 0];
            $tally[$id]['count']++;
            $tally[$id]['reached'] = max($tally[$id]['reached'], $k->created_at->getTimestamp());
        }
        if (! $tally) {
            return null;
        }

        usort($tally, fn ($a, $b) => [$b['count'], $a['reached'], $a['id']] <=> [$a['count'], $b['reached'], $b['id']]);
        $users = User::query()->whereIn('id', array_column($tally, 'id'))->get()->keyBy('id');
        foreach ($tally as $row) {
            $user = $users->get($row['id']);
            if ($user && $this->eligibility->canReceive($user)) {
                return [$row['id'], $row['count']];
            }
        }

        return null;
    }

    private function reward(Spotlight $spotlight): void
    {
        // Claim it: an overlapping run that loses the claim leaves the rewards to this one.
        $claimed = Spotlight::query()->whereKey($spotlight->id)->whereNull('rewarded_at')->update(['rewarded_at' => Carbon::now()]);
        if (! $claimed) {
            return;
        }

        try {
            /** @var User|null $user */
            $user = $spotlight->user_id ? User::query()->find($spotlight->user_id) : null;
            if ($user) {
                $this->addGroup($spotlight, $user);
                $this->badge($spotlight, $user);
                $this->notifications->sync(new SpotlightBlueprint($user, $spotlight->weekKey(), (int) $spotlight->count), [$user]);
            }
        } catch (\Throwable $e) {
            // Unclaim, so the next run tries again (a group already joined is on the row).
            Spotlight::query()->whereKey($spotlight->id)->update(['rewarded_at' => null]);
            $this->log->error('[kudos] spotlight rewards failed: '.$e->getMessage());

            throw $e;
        }
    }

    /**
     * The spotlight group (optional), worn for the week after: joined here,
     * left again by expireGroups(). A member who was in the group before we
     * added them is never taken out of it.
     */
    private function addGroup(Spotlight $spotlight, User $user): void
    {
        $groupId = $this->settings->groupId();
        if (! $groupId || ! Group::query()->whereKey($groupId)->exists()) {
            return;
        }

        $this->db->transaction(function () use ($spotlight, $user, $groupId) {
            $inGroup = $this->db->table('group_user')->where('group_id', $groupId)->where('user_id', $user->id)->exists();
            $ours = Spotlight::query()->where('user_id', $user->id)->where('group_id', $groupId)->whereKeyNot($spotlight->id);
            if (! $inGroup) {
                $this->db->table('group_user')->insertOrIgnore(['group_id' => $groupId, 'user_id' => $user->id]);
            } elseif (! (clone $ours)->exists()) {
                // In it already, not because of us: leave it be.
                return;
            }
            // Ours to take back later, recorded with the join (spotlit again while wearing it: this week owes it now).
            $ours->update(['group_id' => null]);
            Spotlight::query()->whereKey($spotlight->id)->update(['group_id' => $groupId]);
        });
    }

    /** Take the group back from every spotlight made before the current week began. */
    private function expireGroups(Carbon $weekStart): void
    {
        foreach (Spotlight::query()->whereNotNull('group_id')->where('created_at', '<', $weekStart)->get() as $old) {
            if ($old->user_id) {
                $this->db->table('group_user')->where('group_id', $old->group_id)->where('user_id', $old->user_id)->delete();
            }
            Spotlight::query()->whereKey($old->id)->update(['group_id' => null]);
        }
    }

    /** A FoF Badges badge (optional, guarded): kept for good, given once. */
    private function badge(Spotlight $spotlight, User $user): void
    {
        $badgeId = $this->settings->badgeId();
        if (! $badgeId || ! $this->extensions->isEnabled('fof-badges') || ! class_exists(\FoF\Badges\BadgeAwarder::class)) {
            return;
        }
        $badge = \FoF\Badges\Badge::query()->whereKey($badgeId)->whereNull('trigger_config')->first();
        if (! $badge) {
            return;
        }
        [$userBadge, $created] = resolve(\FoF\Badges\BadgeAwarder::class)->award($user, $badge, \FoF\Badges\UserBadge::GRANTED_BY_API);
        if ($created) {
            $userBadge->reason = mb_substr((string) resolve('translator')->trans('ernestdefoe-kudos.lib.badge_reason', ['{week}' => $spotlight->weekKey()]), 0, 500);
            $userBadge->save();
        }
        Spotlight::query()->whereKey($spotlight->id)->update(['badge_id' => (int) $badge->id]);
    }

    /**
     * The latest spotlight, cached: week, member, count and when it was made
     * (the banner shows it during the week it was made in). 0 queries warm.
     *
     * @return array{week: string, userId: int, count: int, created: int}|null
     */
    public function latest(): ?array
    {
        $value = $this->cache->rememberForever(self::CACHE_KEY, function () {
            $row = Spotlight::query()->whereNotNull('user_id')->orderByDesc('week')->first();

            return $row ? ['week' => $row->weekKey(), 'userId' => (int) $row->user_id, 'count' => (int) $row->count, 'created' => $row->created_at->getTimestamp()] : false;
        });

        return is_array($value) && isset($value['created']) ? $value : null;
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
