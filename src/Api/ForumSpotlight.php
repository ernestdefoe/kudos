<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Presenter;
use ErnestDefoe\Kudos\Settings;
use ErnestDefoe\Kudos\Spotlights;
use ErnestDefoe\Kudos\Weeks;
use Flarum\User\User;

/**
 * The index banner: the spotlight made this week (for the week before). The
 * spotlight itself is cached (0 queries); the member is looked up through
 * whereVisibleTo (1 indexed query) only when there's a banner to show.
 */
class ForumSpotlight
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Weeks $weeks,
        private readonly Spotlights $spotlights,
        private readonly Presenter $presenter,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function for(User $actor): ?array
    {
        if (! $this->settings->banner() || ! Access::canView($actor)) {
            return null;
        }
        $latest = $this->spotlights->latest();
        // Shown during the week it was made in (the week after the one it's for).
        if (! $latest || $latest['created'] < $this->weeks->current()->start->getTimestamp()) {
            return null;
        }
        /** @var User|null $user */
        $user = User::query()->whereVisibleTo($actor)->find($latest['userId']);

        return $user ? ['week' => $latest['week'], 'count' => $latest['count'], 'user' => $this->presenter->user($user)] : null;
    }
}
