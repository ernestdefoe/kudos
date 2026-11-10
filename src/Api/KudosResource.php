<?php

namespace ErnestDefoe\Kudos\Api;

use ErnestDefoe\Kudos\Access;
use ErnestDefoe\Kudos\Kudos;
use Flarum\Api\Context;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * The `kudos` JSON:API type, with no endpoints of its own: the forum reads
 * kudos through the plain-JSON controllers. It exists because a
 * notification's subject must have a registered resource, or every
 * notification list 500s. Visibility follows Kudos::scopeVisibleTo.
 *
 * @extends AbstractDatabaseResource<Kudos>
 */
class KudosResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'kudos';
    }

    public function model(): string
    {
        return Kudos::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        /** @var Builder<Kudos> $query */
        $query->visibleTo(RequestUtil::getActor($context->request));
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [
            // A note a moderator removed is gone from the alert too.
            Schema\Str::make('note')->nullable()->get(fn (Kudos $k, Context $context) => $k->note_hidden_at && ! Access::canModerate($context->getActor()) ? null : $k->note),
            Schema\Str::make('style')->get(fn (Kudos $k) => $k->style),
            Schema\Str::make('week')->get(fn (Kudos $k) => $k->weekKey()),
        ];
    }
}
