<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Presenter;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/kudos/recipients?q=sam: who the give form can pick. Members the
 * giver can see whose username (or nickname) starts with the text, never
 * the giver. Givers with core's searchUsers permission only, so it can't
 * be used to list members anyone couldn't already search for.
 */
class RecipientsController extends Handler
{
    public function __construct(
        private readonly Presenter $presenter,
        private readonly ExtensionManager $extensions,
        private readonly \ErnestDefoe\Kudos\Eligibility $eligibility,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        // Core's own rule for looking members up by name applies here too.
        if (! Access::canGive($actor) || ! $actor->can('searchUsers')) {
            return new JsonResponse(['users' => []]);
        }
        $q = trim(mb_substr(self::query($request, 'q'), 0, 40));
        if ($q === '') {
            return new JsonResponse(['users' => []]);
        }
        $like = addcslashes($q, '%_\\').'%';
        $nicknames = $this->extensions->isEnabled('flarum-nicknames');

        $users = User::query()->whereVisibleTo($actor)
            ->where('users.id', '!=', $actor->id)
            ->tap(fn (Builder $q) => $this->eligibility->scopeReceivers($q))
            ->where(function (Builder $w) use ($like, $nicknames) {
                $w->where('users.username', 'like', $like);
                if ($nicknames) {
                    $w->orWhere('users.nickname', 'like', $like);
                }
            })
            ->orderByDesc('users.last_seen_at')
            ->limit(8)->get();

        return new JsonResponse(['users' => $users->map(fn (User $u) => $this->presenter->user($u))->values()->all()]);
    }
}
