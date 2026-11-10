<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Wallet;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /api/kudos/me: the give form's kudos left and who was thanked already this week. */
class MeController extends Handler
{
    public function __construct(private readonly Wallet $wallet)
    {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        return new JsonResponse(['me' => $this->wallet->for($actor)]);
    }
}
