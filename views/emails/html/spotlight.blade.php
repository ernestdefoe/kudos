<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans('ernestdefoe-kudos.email.spotlight.html.body', [
            '{count}' => $blueprint->count,
            '{url}' => $url->to('forum')->route('kudos').'?week='.$blueprint->week,
        ])) !!}
    </x-slot:body>
</x-mail::html.notification>
