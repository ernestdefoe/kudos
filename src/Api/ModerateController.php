<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Kudos;
use ErnestDefoe\Kudos\Moderation;
use ErnestDefoe\Kudos\Presenter;
use ErnestDefoe\Kudos\Refusal;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * PATCH /api/kudos/{id} {action: approve | hide_note | restore_note}
 * DELETE /api/kudos/{id}
 * Moderators only. A giver can also withdraw their own kudos while it waits in the queue.
 */
class ModerateController extends Handler
{
    public function __construct(
        private readonly Moderation $moderation,
        private readonly Presenter $presenter,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        /** @var Kudos $kudos */
        $kudos = Kudos::query()->visibleTo($actor)->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));

        if ($request->getMethod() === 'DELETE') {
            $ownPending = ! $actor->isGuest() && (int) $kudos->giver_id === (int) $actor->id && $kudos->status === Kudos::PENDING;
            if (! $ownPending) {
                Access::assertModerate($actor);
            }
            $this->moderation->delete($kudos);

            return new EmptyResponse(204);
        }

        Access::assertModerate($actor);
        match (Arr::get(self::body($request), 'action')) {
            'approve' => $this->moderation->approve($kudos),
            'hide_note' => $this->moderation->hideNote($kudos, $actor),
            'restore_note' => $this->moderation->restoreNote($kudos),
            default => throw new Refusal('kudos_bad_action'),
        };

        return new JsonResponse(['card' => $this->presenter->cards(new Collection([$kudos->fresh()]), $actor)[0]]);
    }
}
