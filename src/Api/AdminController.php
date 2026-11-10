<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Settings;
use Flarum\Extension\ExtensionManager;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/kudos/admin: what the settings page needs beyond the settings:
 * the FoF Badges badges a spotlight can give (when FoF Badges is on; only
 * badges without a trigger, which FoF Badges would take back again) and when
 * the scheduler last ran. Admins only.
 */
class AdminController extends Handler
{
    public function __construct(
        private readonly ExtensionManager $extensions,
        private readonly Settings $settings,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        $actor->assertAdmin();

        $badges = null;
        if ($this->extensions->isEnabled('fof-badges') && class_exists(\FoF\Badges\Badge::class)) {
            $badges = \FoF\Badges\Badge::query()->whereNull('trigger_config')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($b) => ['id' => (int) $b->id, 'name' => (string) $b->name])->all();
        }

        $last = $this->settings->lastRun();

        return new JsonResponse([
            'badges' => $badges,
            'lastRun' => $last ? date(DATE_ATOM, $last) : null,
        ]);
    }
}
