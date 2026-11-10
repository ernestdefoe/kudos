<?php

/*
 * Kudos Wall: members thank each other with a few kudos cards a week.
 *
 * A give (Giving) is judged on the server under the giver's row lock: the
 * week's allowance, never yourself, one per person per week. The wall,
 * profiles and the banner only ever show people and posts the viewer can
 * see (Presenter), and nothing at all where the forum is closed to them
 * (Access). Every hour the scheduler puts the week that just ended's
 * most-thanked member in the spotlight, exactly once (Spotlights).
 *
 * forum.js only wires routes, links, alerts and the banner; the wall, the
 * profile tab and the give form are one lazy chunk.
 */

use ErnestDefoe\Kudos\Api;
use ErnestDefoe\Kudos\Console\SpotlightCommand;
use ErnestDefoe\Kudos\Gdpr\KudosData;
use ErnestDefoe\Kudos\Http\PageContent;
use ErnestDefoe\Kudos\Notification\KudosReceivedBlueprint;
use ErnestDefoe\Kudos\Notification\SpotlightBlueprint;
use Flarum\Api\Context;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Gdpr\Extend\UserData;
use Flarum\User\Event\Deleted;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        // 🚨 Not optional: the wall and the give form are a chunk in here, and undeclared chunks 404.
        ->jsDirectory(__DIR__.'/js/dist/forum')
        ->css(__DIR__.'/less/forum.less')
        ->css(__DIR__.'/less/cards.less')
        ->route('/kudos', 'kudos', PageContent::class),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\View())
        ->namespace('ernestdefoe-kudos', __DIR__.'/views'),

    (new Extend\Routes('api'))
        ->get('/kudos', 'kudos.wall', Api\WallController::class)
        ->post('/kudos', 'kudos.give', Api\GiveController::class)
        ->get('/kudos/me', 'kudos.me', Api\MeController::class)
        ->get('/kudos/recipients', 'kudos.recipients', Api\RecipientsController::class)
        ->get('/kudos/admin', 'kudos.admin', Api\AdminController::class)
        ->get('/kudos/users/{id:\d+}', 'kudos.user', Api\ProfileController::class)
        ->patch('/kudos/{id:\d+}', 'kudos.moderate', Api\ModerateController::class)
        ->delete('/kudos/{id:\d+}', 'kudos.delete', Api\ModerateController::class),

    new Extend\ApiResource(Api\KudosResource::class),

    // The viewer's own permissions (already loaded, no query) and the banner (cached; one query when there is one).
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('kudosCanView')
                ->get(fn ($forum, Context $context) => \ErnestDefoe\Kudos\Access::canView($context->getActor())),
            Schema\Boolean::make('kudosCanGive')
                ->get(fn ($forum, Context $context) => \ErnestDefoe\Kudos\Access::canGive($context->getActor())),
            Schema\Boolean::make('kudosCanModerate')
                ->get(fn ($forum, Context $context) => \ErnestDefoe\Kudos\Access::canModerate($context->getActor())),
            Schema\Arr::make('kudosSpotlight')
                ->nullable()
                ->get(fn ($forum, Context $context) => resolve(Api\ForumSpotlight::class)->for($context->getActor())),
        ]),

    (new Extend\Notification())
        ->type(KudosReceivedBlueprint::class, ['alert'])
        ->type(SpotlightBlueprint::class, ['alert']),

    // 🚨 String literals, not Settings:: constants: extend.php runs while a deploy
    // swaps vendor/, and a class constant read here fatals every request then.
    (new Extend\Settings())
        ->default('ernestdefoe-kudos.nav', true)
        ->default('ernestdefoe-kudos.banner', true)
        ->default('ernestdefoe-kudos.allowance', 3)
        ->default('ernestdefoe-kudos.start_day', 1)
        ->default('ernestdefoe-kudos.moderation', false)
        ->serializeToForum('kudosNav', 'ernestdefoe-kudos.nav', 'boolval'),

    (new Extend\Console())
        ->command(SpotlightCommand::class)
        ->schedule(SpotlightCommand::class, function (ScheduledEvent $event) {
            $event->hourly()->withoutOverlapping();
        }),

    // The foreign keys do this too, where the database enforces them (SQLite may not).
    (new Extend\Event())
        ->listen(Deleted::class, function (Deleted $event) {
            $id = $event->user->id;
            \ErnestDefoe\Kudos\Kudos::query()->where('giver_id', $id)->update(['giver_id' => null]);
            \ErnestDefoe\Kudos\Kudos::query()->where('receiver_id', $id)->delete();
            \ErnestDefoe\Kudos\Spotlight::query()->where('user_id', $id)->update(['user_id' => null]);
            resolve('db')->table('kudos_givers')->where('user_id', $id)->delete();
            resolve(\ErnestDefoe\Kudos\Spotlights::class)->forget();
        }),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-gdpr', fn () => [
            (new UserData())->addType(KudosData::class),
        ]),
];
