<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('ernestdefoe-kudos.email.spotlight.plain.body', [
'{count}' => $blueprint->count,
'{url}' => $url->to('forum')->route('kudos').'?week='.$blueprint->week,
]) !!}
</x-slot:body>
</x-mail::plain.notification>
