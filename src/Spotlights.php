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
 * Idempotent: the week is a unique key and only the run whose insert lands
 * hands anything out; rewards are stamped (`rewarded_at`) when done, so a
 * run that failed half way finishes them next time, and each reward checks
 * before it acts (a badge is never given twice, a group joined once). Only
 * the week that just ended is ever judged: a scheduler that was down for a
 * month doesn't wake up and spotlight four old weeks at once.
 *
 * Ties go to whoever reached the count first.
 */
class Spotlights
{
    public const CACHE_KEY = 'ernestdefoe-kudos.spotlight';

    public function __construct(
        private readonly Settings $settings,
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
        $week = Week::current($this->settings)->previous()->key();
        $made = null;

        if (! Spotlight::query()->where('week', $week)->exists()) {
            $top = Kudos::query()
                ->where('week', $week)->where('status', Kudos::APPROVED)
                ->selectRaw('receiver_id, COUNT(*) as total, MAX(created_at) as reached')
                ->groupBy('receiver_id')
                ->orderByDesc('total')->orderBy('reached')->orderBy('receiver_id')
                ->first();

            if ($top) {
                $inserted = $this->db->table('kudos_spotlights')->insertOrIgnore([
                    'week' => $week,
                    'user_id' => (int) $top->receiver_id,
                    'count' => (int) $top->getAttribute('total'),
                    'created_at' => Carbon::now(),
                ]);
                if ($inserted) {
                    $made = Spotlight::query()->where('week', $week)->first();
                    $this->forget();
                }
            }
        }

        // This week's (or a retry of a failed reward run): never older weeks.
        $due = Spotlight::query()->where('week', $week)->whereNull('rewarded_at')->first();
        if ($due) {
            $this->reward($due);
        }
        $this->expireGroups($week);

        return $made;
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
            $spotlight->save();
        } catch (\Throwable $e) {
            // Unclaim, so the next run tries again.
            Spotlight::query()->whereKey($spotlight->id)->update(['rewarded_at' => null]);
            $this->log->error('[kudos] spotlight rewards failed: '.$e->getMessage());

            throw $e;
        }
    }

    /**
     * The spotlight group (optional), worn for the week after: joined here,
     * left again by expireGroups() once a newer week is judged. A member who
     * was in the group before we added them is never taken out of it.
     */
    private function addGroup(Spotlight $spotlight, User $user): void
    {
        $groupId = $this->settings->groupId();
        if (! $groupId || ! Group::query()->whereKey($groupId)->exists()) {
            return;
        }

        $ours = Spotlight::query()->where('user_id', $user->id)->where('group_id', $groupId)->whereKeyNot($spotlight->id);
        if (! $this->db->table('group_user')->where('group_id', $groupId)->where('user_id', $user->id)->exists()) {
            $this->db->table('group_user')->insertOrIgnore(['group_id' => $groupId, 'user_id' => $user->id]);
            $spotlight->group_id = $groupId;
        } elseif ((clone $ours)->exists()) {
            // Spotlit again while still wearing it: the newer week owes taking it back.
            $ours->update(['group_id' => null]);
            $spotlight->group_id = $groupId;
        }
    }

    /** Take the group back from every spotlight older than the week just judged. */
    private function expireGroups(string $week): void
    {
        foreach (Spotlight::query()->whereNotNull('group_id')->where('week', '<', $week)->get() as $old) {
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
        $spotlight->badge_id = (int) $badge->id;
    }

    /**
     * The latest spotlight for the banner, cached: [week, user id, count] or
     * null. 0 queries on a warm cache.
     *
     * @return array{week: string, userId: int, count: int}|null
     */
    public function latest(): ?array
    {
        $value = $this->cache->rememberForever(self::CACHE_KEY, function () {
            $row = Spotlight::query()->whereNotNull('user_id')->orderByDesc('week')->first();

            return $row ? ['week' => $row->weekKey(), 'userId' => (int) $row->user_id, 'count' => (int) $row->count] : false;
        });

        return is_array($value) ? $value : null;
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}
