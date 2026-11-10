<?php

namespace ErnestDefoe\Kudos;

use Flarum\Discussion\Discussion;
use Flarum\Http\SlugManager;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * Cards and people as the browser gets them.
 *
 * 🚨 Privacy: a member the viewer can't see (User::whereVisibleTo) is shown
 * as "a member", with no name, avatar or link; a linked post the viewer
 * can't read is left off; a hidden note is gone for everyone but
 * moderators. People and posts for a whole page are loaded in one query
 * each, never per card.
 */
class Presenter
{
    /** @var array<int, User|false> users already resolved for this viewer */
    private array $people = [];

    public function __construct(private readonly SlugManager $slugs)
    {
    }

    /** Load (once) the members among $ids the viewer may see. */
    public function prime(User $actor, iterable $ids): void
    {
        $missing = [];
        foreach ($ids as $id) {
            if ($id && ! array_key_exists((int) $id, $this->people)) {
                $missing[(int) $id] = (int) $id;
            }
        }
        if (! $missing) {
            return;
        }
        $found = User::query()->whereVisibleTo($actor)->whereIn('users.id', array_values($missing))->get()->keyBy('id');
        foreach ($missing as $id) {
            $this->people[$id] = $found->get($id) ?? false;
        }
    }

    /** @return array<string, mixed>|null null = nobody (account gone); hidden = someone the viewer can't see. */
    public function person(?int $id): ?array
    {
        if (! $id) {
            return null;
        }
        $user = $this->people[$id] ?? false;
        if (! $user) {
            return ['id' => null, 'hidden' => true];
        }

        return $this->user($user);
    }

    /** @return array{id: int, username: string, displayName: string, slug: string, avatarUrl: string|null} */
    public function user(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'displayName' => $user->display_name,
            'slug' => $this->slugs->forResource(User::class)->toSlug($user),
            'avatarUrl' => $user->avatar_url,
        ];
    }

    /**
     * @param Collection<int, Kudos> $kudos
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $kudos, User $actor): array
    {
        $this->prime($actor, $kudos->flatMap(fn (Kudos $k) => [$k->giver_id, $k->receiver_id]));

        $postIds = $kudos->pluck('post_id')->filter()->unique()->values()->all();
        $posts = $postIds
            ? Post::query()->whereVisibleTo($actor)->where('posts.type', CommentPost::$type)->whereIn('posts.id', $postIds)->with('discussion')->get()->keyBy('id')
            : new Collection();

        $moderator = Access::canModerate($actor);

        $cards = [];
        foreach ($kudos as $k) {
            /** @var Post|null $post */
            $post = $k->post_id ? $posts->get($k->post_id) : null;
            /** @var Discussion|null $discussion */
            $discussion = $post ? $post->discussion : null;
            $hidden = $k->note_hidden_at !== null;

            $cards[] = [
                'id' => (int) $k->id,
                'week' => $k->weekKey(),
                'style' => $k->style,
                'note' => $hidden && ! $moderator ? null : $k->note,
                'noteHidden' => $hidden,
                'pending' => $k->status === Kudos::PENDING,
                'mine' => ! $actor->isGuest() && (int) $k->giver_id === (int) $actor->id,
                'createdAt' => $k->created_at->toIso8601String(),
                'giver' => $this->person($k->giver_id),
                'receiver' => $this->person($k->receiver_id),
                'post' => $post && $discussion ? [
                    'id' => (int) $post->id,
                    'number' => (int) $post->number,
                    'discussionId' => (int) $discussion->id,
                    'slug' => $this->slugs->forResource(Discussion::class)->toSlug($discussion),
                    'title' => (string) $discussion->title,
                ] : null,
            ];
        }

        return $cards;
    }
}
