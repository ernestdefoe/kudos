{{-- Member text (the note) never goes through Markdown: placeholders are formatted, then swapped for the escaped text. --}}
<x-mail::html.notification>
    <x-slot:body>
        {!! strtr($formatter->convert($translator->trans('ernestdefoe-kudos.email.received.html.body', [
            '{giver}' => 'KUDOSGIVERX',
            '{note}' => 'KUDOSNOTEX',
            '{url}' => $url->to('forum')->route('kudos'),
        ])), [
            'KUDOSGIVERX' => e($blueprint->giver?->display_name ?? $translator->trans('ernestdefoe-kudos.email.a_member')),
            'KUDOSNOTEX' => e($blueprint->kudos->note_hidden_at ? '' : $blueprint->kudos->note),
        ]) !!}
    </x-slot:body>
</x-mail::html.notification>
