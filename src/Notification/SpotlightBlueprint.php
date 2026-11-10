<?php

namespace ErnestDefoe\Kudos\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;

/**
 * "You were the most-thanked member last week". The subject is the member
 * (a User has an API resource); the week in the data makes it one alert per
 * week, so a second scheduler run sends nothing again.
 */
class SpotlightBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(public User $user, public string $week, public int $count)
    {
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->user;
    }

    public function getData(): mixed
    {
        return ['week' => $this->week, 'count' => $this->count];
    }

    public static function getType(): string
    {
        return 'kudosSpotlight';
    }

    public static function getSubjectModel(): string
    {
        return User::class;
    }

    public function getEmailViews(): array
    {
        return [
            'text' => 'ernestdefoe-kudos::emails.plain.spotlight',
            'html' => 'ernestdefoe-kudos::emails.html.spotlight',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('ernestdefoe-kudos.email.spotlight.subject');
    }
}
