<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use ErnestDefoe\Kudos\Notification\KudosReceivedBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Giving a kudos.
 *
 * 🚨 Race-safe: the giver's `kudos_givers` row is locked (SELECT … FOR
 * UPDATE) and the week's allowance and "already thanked them this week" are
 * counted again inside the transaction, so two gives fired at once can't
 * both pass. The row is created before the transaction (an INSERT IGNORE
 * inside it would take a shared lock and deadlock two racing gives). The
 * unique (giver, receiver, week) key backs the one-a-week rule up.
 *
 * The allowance is counted from the week's rows, never kept as a counter:
 * a kudos a moderator deletes gives its kudos back by itself.
 */
class Giving
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ConnectionInterface $db,
        private readonly NotificationSyncer $notifications,
        private readonly Weeks $weeks,
        private readonly Eligibility $eligibility,
    ) {
    }

    /** @param array<string, mixed> $input receiver, note, post (optional), style */
    public function give(User $actor, array $input): Kudos
    {
        // Unconfirmed members have no group permissions at all, so say what's actually missing first.
        if (! $actor->isGuest() && ! $actor->is_email_confirmed) {
            throw new Refusal('kudos_confirm_email', 403);
        }
        if (! Access::canGive($actor)) {
            throw new Refusal('kudos_cannot_give', 403);
        }

        $receiverId = is_numeric($input['receiver'] ?? null) ? (int) $input['receiver'] : 0;
        if ($receiverId === (int) $actor->id) {
            throw new Refusal('kudos_self');
        }
        /** @var User|null $receiver */
        $receiver = User::query()->whereVisibleTo($actor)->find($receiverId);
        if (! $receiver) {
            throw new Refusal('kudos_no_receiver');
        }
        // Unconfirmed and suspended accounts can't be thanked (nor spotlit).
        if (! $this->eligibility->canReceive($receiver)) {
            throw new Refusal('kudos_receiver_unavailable');
        }

        $note = self::cleanNote($input['note'] ?? null);
        if (mb_strlen($note) < Settings::NOTE_MIN) {
            throw new Refusal('kudos_note_short');
        }
        if (mb_strlen($note) > Settings::NOTE_MAX) {
            throw new Refusal('kudos_note_long');
        }

        $style = is_string($input['style'] ?? null) && in_array($input['style'], Settings::STYLES, true) ? $input['style'] : Settings::STYLES[0];

        // The post that helped: a comment by the receiver that the giver can see.
        $postId = null;
        if (! empty($input['post'])) {
            $post = is_numeric($input['post'])
                ? Post::query()->whereVisibleTo($actor)->where('posts.type', CommentPost::$type)->find((int) $input['post'])
                : null;
            if (! $post || (int) $post->user_id !== $receiverId) {
                throw new Refusal('kudos_bad_post');
            }
            $postId = (int) $post->id;
        }

        $allowance = $this->settings->allowanceFor($actor);
        $week = $this->weeks->current()->key();
        $queued = $this->settings->moderation() && ! Access::canModerate($actor);

        $this->db->table('kudos_givers')->insertOrIgnore(['user_id' => $actor->id]);

        try {
            /** @var Kudos $kudos */
            $kudos = $this->db->transaction(function () use ($actor, $receiverId, $week, $allowance, $note, $postId, $style, $queued) {
                $this->db->table('kudos_givers')->where('user_id', $actor->id)->lockForUpdate()->first();

                $given = Kudos::query()->where('giver_id', $actor->id)->where('week', $week)->pluck('receiver_id');
                if ($given->count() >= $allowance) {
                    throw new Refusal('kudos_none_left');
                }
                if ($given->contains(fn ($id) => (int) $id === $receiverId)) {
                    throw new Refusal('kudos_already_this_week');
                }

                $now = Carbon::now();
                $kudos = new Kudos();
                $kudos->giver_id = (int) $actor->id;
                $kudos->receiver_id = $receiverId;
                $kudos->week = $week;
                $kudos->note = $note;
                $kudos->post_id = $postId;
                $kudos->style = $style;
                $kudos->status = $queued ? Kudos::PENDING : Kudos::APPROVED;
                $kudos->created_at = $now;
                $kudos->approved_at = $queued ? null : $now;
                $kudos->save();

                return $kudos;
            });
        } catch (UniqueConstraintViolationException) {
            throw new Refusal('kudos_already_this_week');
        }

        if ($kudos->status === Kudos::APPROVED) {
            $this->notify($kudos, $actor, $receiver);
        }

        return $kudos;
    }

    /** "Sam gave you kudos": once per kudos, when it shows on the wall. */
    public function notify(Kudos $kudos, ?User $giver = null, ?User $receiver = null): void
    {
        $receiver ??= $kudos->receiver;
        if ($receiver) {
            $this->notifications->sync(new KudosReceivedBlueprint($kudos, $giver ?? $kudos->giver), [$receiver]);
        }
    }

    /** One line of plain text: control characters, direction overrides and runs of spaces go; the words (and emoji) stay as typed. */
    public static function cleanNote(mixed $note): string
    {
        if (! is_string($note)) {
            return '';
        }
        $note = (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', ' ', $note);

        return trim((string) preg_replace('/\s{2,}/u', ' ', $note));
    }
}
