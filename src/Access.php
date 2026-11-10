<?php

namespace ErnestDefoe\Kudos;

use Flarum\User\Exception\NotAuthenticatedException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;

/**
 * Who may see kudos: anyone who can see the forum AND has kudos.view. A forum
 * closed to guests keeps the wall, profiles and spotlight closed too. Every
 * endpoint, the forum flags and the notification subject go through here.
 */
final class Access
{
    public static function canView(User $actor): bool
    {
        return $actor->can('viewForum') && $actor->can(Settings::VIEW);
    }

    public static function canGive(User $actor): bool
    {
        return ! $actor->isGuest() && self::canView($actor) && $actor->can(Settings::GIVE);
    }

    public static function canModerate(User $actor): bool
    {
        return ! $actor->isGuest() && self::canView($actor) && $actor->can(Settings::MODERATE);
    }

    public static function assertView(User $actor): void
    {
        if (! self::canView($actor)) {
            throw new PermissionDeniedException();
        }
    }

    public static function assertModerate(User $actor): void
    {
        if ($actor->isGuest()) {
            throw new NotAuthenticatedException();
        }
        if (! self::canModerate($actor)) {
            throw new PermissionDeniedException();
        }
    }
}
