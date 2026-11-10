<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thank-you card.
 *
 * @property int $id
 * @property int|null $giver_id
 * @property int $receiver_id
 * @property string $week the week's first day, Y-m-d (no date cast: SQLite would store a time with it and the week would stop matching)
 * @property string $note
 * @property int|null $post_id
 * @property string $style
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $note_hidden_at
 * @property int|null $note_hidden_by
 * @property User|null $giver
 * @property User|null $receiver
 * @property Post|null $post
 */
class Kudos extends AbstractModel
{
    public const APPROVED = 'approved';
    public const PENDING = 'pending';

    protected $table = 'kudos';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'note_hidden_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function giver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'giver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function weekKey(): string
    {
        return substr((string) ($this->getAttributes()['week'] ?? ''), 0, 10);
    }

    /**
     * What the actor may see: approved kudos, plus their own while they wait
     * for a moderator, plus the whole queue for moderators. Nothing at all
     * without the forum and kudos.view.
     *
     * @param Builder<Kudos> $query
     */
    public function scopeVisibleTo(Builder $query, User $actor): void
    {
        if (! Access::canView($actor)) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (Access::canModerate($actor)) {
            return;
        }
        $query->where(function (Builder $q) use ($actor) {
            $q->where('kudos.status', self::APPROVED);
            if (! $actor->isGuest()) {
                $q->orWhere('kudos.giver_id', $actor->id);
            }
        });
    }
}
