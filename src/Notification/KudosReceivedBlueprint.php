<?php

namespace ErnestDefoe\Kudos\Notification;

use ErnestDefoe\Kudos\Kudos;
use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * "Sam gave you kudos". The subject is the kudos, which has the `kudos` API
 * resource (a subject without one 500s every notification list) and follows
 * its visibility: a deleted kudos takes its alert with it, a hidden note
 * never shows in the excerpt. No data, so one kudos is one alert, however
 * often it's synced. Email is the member's choice (off by default).
 */
class KudosReceivedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public Kudos $kudos, public ?User $giver)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->giver;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->kudos;
    }

    public function getData(): mixed
    {
        return null;
    }

    public static function getType(): string
    {
        return 'kudosReceived';
    }

    public static function getSubjectModel(): string
    {
        return Kudos::class;
    }

    public function getEmailViews(): array
    {
        return [
            'text' => 'ernestdefoe-kudos::emails.plain.received',
            'html' => 'ernestdefoe-kudos::emails.html.received',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-kudos.email.received.subject', ['{giver}' => $this->giver->display_name ?? $translator->trans('ernestdefoe-kudos.email.a_member')]);
    }
}
