<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('ernestdefoe-kudos.email.received.plain.body', [
'{giver}' => $blueprint->giver?->display_name ?? $translator->trans('ernestdefoe-kudos.email.a_member'),
'{note}' => $blueprint->kudos->note_hidden_at ? '' : $blueprint->kudos->note,
'{url}' => $url->to('forum')->route('kudos'),
]) !!}
</x-slot:body>
</x-mail::plain.notification>
