<?php

namespace ErnestDefoe\Kudos;

use Flarum\User\User;

/** What the member has left this week, and who they've thanked already (the give form greys them out). */
class Wallet
{
    public function __construct(private readonly Settings $settings)
    {
    }

    /** @return array{allowance: int, used: int, left: int, givenTo: list<int>, resetsAt: string, canGive: bool}|null */
    public function for(User $actor): ?array
    {
        if (! Access::canGive($actor)) {
            return null;
        }
        $week = Week::current($this->settings);
        $givenTo = Kudos::query()->where('giver_id', $actor->id)->where('week', $week->key())->pluck('receiver_id')->map(fn ($id) => (int) $id)->values()->all();
        $allowance = $this->settings->allowanceFor($actor);

        return [
            'allowance' => $allowance,
            'used' => count($givenTo),
            'left' => max(0, $allowance - count($givenTo)),
            'givenTo' => $givenTo,
            'resetsAt' => $week->end()->utc()->toIso8601String(),
            'canGive' => (bool) $actor->is_email_confirmed,
        ];
    }
}
