<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Giving;
use ErnestDefoe\Kudos\Presenter;
use ErnestDefoe\Kudos\Wallet;
use Flarum\User\User;
use Illuminate\Support\Collection;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** POST /api/kudos {receiver, note, post?, style}. Everything that decides it is in Giving, under the lock. */
class GiveController extends Handler
{
    public function __construct(
        private readonly Giving $giving,
        private readonly Presenter $presenter,
        private readonly Wallet $wallet,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        $kudos = $this->giving->give($actor, self::body($request));

        return new JsonResponse([
            'card' => $this->presenter->cards(new Collection([$kudos]), $actor)[0],
            'me' => $this->wallet->for($actor),
        ], 201);
    }
}
