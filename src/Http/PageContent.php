<?php

namespace ErnestDefoe\Kudos\Http;

use Flarum\Frontend\Document;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;

/** The /kudos page as the server sends it: a real title, canonical URL and description. No queries. */
class PageContent
{
    public function __construct(
        private readonly UrlGenerator $url,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Document $document): Document
    {
        $document->title = (string) $this->translator->trans('ernestdefoe-kudos.forum.page.title');
        $document->canonicalUrl = $this->url->to('forum')->route('kudos');
        $document->meta['description'] = (string) $this->translator->trans('ernestdefoe-kudos.forum.page.description');

        return $document;
    }
}
