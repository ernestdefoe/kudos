<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Kudos;
use ErnestDefoe\Kudos\Presenter;
use ErnestDefoe\Kudos\Settings;
use ErnestDefoe\Kudos\Spotlight;
use ErnestDefoe\Kudos\Week;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/kudos/users/{id}[?before=id]: a member's Kudos tab. The kudos
 * they received (24 a page), and on the first page their totals, weeks in
 * the spotlight and the members who thank them most. A member the viewer
 * can't see is a 404, like one that doesn't exist.
 */
class ProfileController extends Handler
{
    private const PAGE = 24;

    public function __construct(
        private readonly Presenter $presenter,
        private readonly Settings $settings,
    ) {
    }

    protected function respond(ServerRequestInterface $request, User $actor): ResponseInterface
    {
        /** @var User $user */
        $user = User::query()->whereVisibleTo($actor)->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $before = (int) self::query($request, 'before');

        $received = Kudos::query()->where('kudos.receiver_id', $user->id)->where('kudos.status', Kudos::APPROVED)
            ->when($before > 0, fn (Builder $q) => $q->where('kudos.id', '<', $before))
            ->orderByDesc('kudos.id')->limit(self::PAGE + 1)->get();
        $more = $received->count() > self::PAGE;
        $received = $received->take(self::PAGE);
        $data = ['next' => $more ? (int) $received->last()->id : null];

        if ($before > 0) {
            return new JsonResponse($data + ['cards' => $this->presenter->cards($received, $actor)]);
        }

        // Top givers: more than five asked for, because some may be members the viewer can't see.
        $top = Kudos::query()->where('receiver_id', $user->id)->where('status', Kudos::APPROVED)->whereNotNull('giver_id')
            ->selectRaw('giver_id, COUNT(*) as total, MAX(id) as latest')->groupBy('giver_id')
            ->orderByDesc('total')->orderByDesc('latest')->limit(12)->toBase()->get();

        $this->presenter->prime($actor, [$user->id, ...$top->pluck('giver_id')->all(), ...$received->flatMap(fn (Kudos $k) => [$k->giver_id, $k->receiver_id])->all()]);

        $givers = [];
        foreach ($top as $row) {
            $person = $this->presenter->person((int) $row->giver_id);
            if ($person && ! ($person['hidden'] ?? false)) {
                $givers[] = ['user' => $person, 'count' => (int) $row->total];
            }
            if (count($givers) === 5) {
                break;
            }
        }

        $counts = Kudos::query()->where('status', Kudos::APPROVED)
            ->where(fn (Builder $q) => $q->where('receiver_id', $user->id)->orWhere('giver_id', $user->id))
            ->selectRaw('SUM(CASE WHEN receiver_id = ? THEN 1 ELSE 0 END) as received, SUM(CASE WHEN giver_id = ? THEN 1 ELSE 0 END) as given, SUM(CASE WHEN receiver_id = ? AND week = ? THEN 1 ELSE 0 END) as week', [$user->id, $user->id, $user->id, Week::current($this->settings)->key()])
            ->toBase()->first();

        return new JsonResponse($data + [
            'user' => $this->presenter->person((int) $user->id),
            'cards' => $this->presenter->cards($received, $actor),
            'received' => (int) ($counts->received ?? 0),
            'given' => (int) ($counts->given ?? 0),
            'thisWeek' => (int) ($counts->week ?? 0),
            'spotlights' => Spotlight::query()->where('user_id', $user->id)->orderByDesc('week')->limit(52)->get()->map(fn (Spotlight $s) => ['week' => $s->weekKey(), 'count' => (int) $s->count])->all(),
            'topGivers' => $givers,
        ]);
    }
}
