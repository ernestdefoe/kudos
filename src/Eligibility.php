<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who can be thanked and spotlit: a confirmed account that isn't suspended
 * (flarum/suspend, only when it's enabled). The same rule picks the
 * recipients the give form offers.
 */
class Eligibility
{
    public function __construct(private readonly ExtensionManager $extensions)
    {
    }

    public function canReceive(User $user): bool
    {
        if (! $user->is_email_confirmed) {
            return false;
        }
        if ($this->suspensions()) {
            $until = $user->getAttribute('suspended_until');

            return ! $until || Carbon::parse($until)->isPast();
        }

        return true;
    }

    /** @param Builder<User> $query */
    public function scopeReceivers(Builder $query): void
    {
        $query->where('users.is_email_confirmed', true);
        if ($this->suspensions()) {
            $query->where(fn (Builder $q) => $q->whereNull('users.suspended_until')->orWhere('users.suspended_until', '<=', Carbon::now()));
        }
    }

    private function suspensions(): bool
    {
        return $this->extensions->isEnabled('flarum-suspend');
    }
}
