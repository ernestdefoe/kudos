<?php

namespace ErnestDefoe\Kudos\Gdpr;

use ErnestDefoe\Kudos\Kudos;
use ErnestDefoe\Kudos\Spotlight;
use ErnestDefoe\Kudos\Spotlights;
use Flarum\Gdpr\Data\Type;

/**
 * A member's export: the kudos they gave (to whom, with their notes), the
 * kudos they received (from whom, with the notes) and their weeks in the
 * spotlight. Erasing deletes all of it. Anonymizing keeps the kudos they
 * gave on others' walls from "a former member", and deletes what they received.
 */
class KudosData extends Type
{
    public static function exportDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-kudos.lib.gdpr.export');
    }

    public static function anonymizeDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-kudos.lib.gdpr.anonymize');
    }

    public static function deleteDescription(): string
    {
        return self::staticTranslator()->trans('ernestdefoe-kudos.lib.gdpr.delete');
    }

    public function export(): ?array
    {
        $row = fn (Kudos $k, string $who, ?string $name) => [
            $who => $name,
            'week' => $k->weekKey(),
            'note' => $k->note,
            'style' => $k->style,
            'status' => $k->status,
            'post_id' => $k->post_id,
            'given_at' => $k->created_at->toIso8601String(),
        ];

        $data = [
            'given' => Kudos::query()->where('giver_id', $this->user->id)->with('receiver')->orderBy('id')->get()
                ->map(fn (Kudos $k) => $row($k, 'to', $k->receiver?->username))->all(),
            'received' => Kudos::query()->where('receiver_id', $this->user->id)->with('giver')->orderBy('id')->get()
                ->map(fn (Kudos $k) => $row($k, 'from', $k->giver?->username))->all(),
            'spotlights' => Spotlight::query()->where('user_id', $this->user->id)->orderBy('week')->get()
                ->map(fn (Spotlight $s) => ['week' => $s->weekKey(), 'kudos' => (int) $s->count])->all(),
        ];

        return [['kudos/kudos.json' => $this->encodeForExport($data)]];
    }

    public function anonymize(): void
    {
        Kudos::query()->where('giver_id', $this->user->id)->update(['giver_id' => null]);
        $this->forgetReceived();
    }

    public function delete(): void
    {
        Kudos::query()->where('giver_id', $this->user->id)->delete();
        $this->forgetReceived();
    }

    private function forgetReceived(): void
    {
        Kudos::query()->where('receiver_id', $this->user->id)->delete();
        Spotlight::query()->where('user_id', $this->user->id)->update(['user_id' => null]);
        resolve('db')->table('kudos_givers')->where('user_id', $this->user->id)->delete();
        resolve(Spotlights::class)->forget();
    }
}
