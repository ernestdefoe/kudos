<?php

namespace ErnestDefoe\Kudos;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The most-thanked member of a finished week.
 *
 * @property int $id
 * @property string $week the week's first day, Y-m-d (no date cast: SQLite would store a time with it and the week would stop matching)
 * @property int|null $user_id
 * @property int $count
 * @property int|null $group_id
 * @property int|null $badge_id
 * @property Carbon $created_at
 * @property Carbon|null $rewarded_at
 * @property User|null $user
 */
class Spotlight extends AbstractModel
{
    protected $table = 'kudos_spotlights';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'rewarded_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function weekKey(): string
    {
        return substr((string) ($this->getAttributes()['week'] ?? ''), 0, 10);
    }
}
