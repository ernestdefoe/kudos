<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use Flarum\User\User;

/**
 * What moderators do: approve a queued kudos, hide (or bring back) a note,
 * delete a kudos. Deleting gives the giver their kudos back if it was this
 * week's, because the allowance is counted from the week's rows.
 */
class Moderation
{
    public function __construct(private readonly Giving $giving)
    {
    }

    public function approve(Kudos $kudos): void
    {
        // Conditional update: two moderators approving at once send one alert.
        $won = Kudos::query()->whereKey($kudos->id)->where('status', Kudos::PENDING)
            ->update(['status' => Kudos::APPROVED, 'approved_at' => Carbon::now()]);
        if ($won) {
            $kudos->refresh();
            $this->giving->notify($kudos);
        }
    }

    public function hideNote(Kudos $kudos, User $actor): void
    {
        $kudos->note_hidden_at = Carbon::now();
        $kudos->note_hidden_by = (int) $actor->id;
        $kudos->save();
    }

    public function restoreNote(Kudos $kudos): void
    {
        $kudos->note_hidden_at = null;
        $kudos->note_hidden_by = null;
        $kudos->save();
    }

    public function delete(Kudos $kudos): void
    {
        $kudos->delete();
        // The receiver's alert goes with it (core hides alerts whose subject is gone, this tidies the row).
        resolve('db')->table('notifications')->where('type', 'kudosReceived')->where('subject_id', $kudos->id)->delete();
    }
}
