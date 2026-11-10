<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Kudos;
use ErnestDefoe\Kudos\Presenter;
use ErnestDefoe\Kudos\Refusal;
use ErnestDefoe\Kudos\Settings;
use ErnestDefoe\Kudos\Spotlight;
use ErnestDefoe\Kudos\Wallet;
use ErnestDefoe\Kudos\Week;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/kudos[?week=2026-11-09][&before=id][&queue=1]
 *
 * One week of the wall, newest first, 48 cards a page. The first page also
 * carries the week's total, its spotlight (for the current week: last
 * week's), the archive of past weeks, the viewer's kudos left and, for
 * moderators, how many wait in the queue. ?queue=1 is the moderators'
 * queue across all weeks. A constant number of queries whatever the size.
 */
class WallController extends Handler
{
    private const PAGE = 48;
    private const ARCHIVE = 26;

    public function __construct(
        private readonly Settings $settings,
        private readonly Presenter $presenter,
        private readonly Wallet $wallet,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        $current = Week::current($this->settings);
        $before = (int) self::query($request, 'before');
        $queue = self::query($request, 'queue') === '1';
        if ($queue) {
            Access::assertModerate($actor);
        }

        $key = self::query($request, 'week');
        $week = $key === '' ? $current : (Week::fromKey($key, $this->settings) ?? throw new Refusal('kudos_bad_week', 404));

        $cards = Kudos::query()->visibleTo($actor)
            ->when($queue, fn (Builder $q) => $q->where('kudos.status', Kudos::PENDING))
            ->when(! $queue, fn (Builder $q) => $q->where('kudos.week', $week->key())->where(function (Builder $q) use ($actor) {
                // The wall: approved kudos, and the viewer's own while they wait.
                $q->where('kudos.status', Kudos::APPROVED);
                if (! $actor->isGuest()) {
                    $q->orWhere('kudos.giver_id', $actor->id);
                }
            }))
            ->when($before > 0, fn (Builder $q) => $q->where('kudos.id', '<', $before))
            ->orderByDesc('kudos.id')
            ->limit(self::PAGE + 1)
            ->get();

        $more = $cards->count() > self::PAGE;
        $cards = $cards->take(self::PAGE);
        $data = ['cards' => [], 'next' => $more ? (int) $cards->last()->id : null];

        if ($before > 0 || $queue) {
            $data['cards'] = $this->presenter->cards($cards, $actor);

            return new JsonResponse($data);
        }

        // The spotlight shown with a week: its own once it's over, last week's while it runs.
        $spotWeek = $week->key() === $current->key() ? $current->previous()->key() : $week->key();
        $spotlight = Spotlight::query()->where('week', $spotWeek)->whereNotNull('user_id')->first();

        $archive = Kudos::query()->where('status', Kudos::APPROVED)->where('week', '<', $current->key())
            ->selectRaw('week, COUNT(*) as total')->groupBy('week')->orderByDesc('week')->limit(self::ARCHIVE)
            ->toBase()->get();
        $archiveKeys = $archive->map(fn ($row) => substr((string) $row->week, 0, 10))->all();
        $spots = $archiveKeys ? Spotlight::query()->whereIn('week', $archiveKeys)->get()->keyBy(fn (Spotlight $s) => $s->weekKey()) : collect();

        $this->presenter->prime($actor, [...$cards->flatMap(fn (Kudos $k) => [$k->giver_id, $k->receiver_id])->all(), $spotlight?->user_id, ...$spots->pluck('user_id')->all()]);
        $data['cards'] = $this->presenter->cards($cards, $actor);

        return new JsonResponse($data + [
            'week' => [
                'key' => $week->key(),
                'start' => $week->start->utc()->toIso8601String(),
                'end' => $week->end()->utc()->toIso8601String(),
                'current' => $week->key() === $current->key(),
                'currentKey' => $current->key(),
                'total' => Kudos::query()->where('week', $week->key())->where('status', Kudos::APPROVED)->count(),
            ],
            'spotlight' => $spotlight ? ['week' => $spotlight->weekKey(), 'count' => (int) $spotlight->count, 'user' => $this->presenter->person($spotlight->user_id)] : null,
            'archive' => $archive->map(function ($row) use ($spots) {
                $key = substr((string) $row->week, 0, 10);
                $spot = $spots->get($key);

                return ['week' => $key, 'count' => (int) $row->total, 'spotlight' => $spot?->user_id ? $this->presenter->person($spot->user_id) : null];
            })->values()->all(),
            'me' => $this->wallet->for($actor),
            'queue' => Access::canModerate($actor) ? Kudos::query()->where('status', Kudos::PENDING)->count() : null,
            'moderation' => $this->settings->moderation(),
        ]);
    }
}
