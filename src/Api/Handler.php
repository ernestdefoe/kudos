<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Refusal;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Every kudos endpoint: the forum and kudos.view first (a forum closed to
 * guests shows guests nothing), then the controller. A Refusal answers with
 * its code; everything else goes to Flarum's error handling.
 */
abstract class Handler implements RequestHandlerInterface
{
    abstract protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        Access::assertView($actor);

        try {
            return $this->respond($request, $actor);
        } catch (Refusal $refusal) {
            return Errors::make($refusal->status, $refusal->reason);
        }
    }

    /** @return array<string, mixed> */
    protected static function body(ServerRequestInterface $request): array
    {
        return (array) ($request->getParsedBody() ?? []);
    }

    protected static function query(ServerRequestInterface $request, string $key): string
    {
        $value = $request->getQueryParams()[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
