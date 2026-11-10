<?php

namespace ErnestDefoe\Kudos\Tests\integration\api;

use Carbon\Carbon;
use ErnestDefoe\Kudos\Gdpr\KudosData;
use ErnestDefoe\Kudos\Kudos;
use ErnestDefoe\Kudos\Spotlight;
use ErnestDefoe\Kudos\Spotlights;
use Flarum\Discussion\Discussion;
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * The rules members can lean on (a few a week, never yourself, one per
 * person per week, counted on the server), privacy (closed forums,
 * moderators' hidden notes, the queue), the weekly spotlight (once, ties,
 * a group worn for a week) and GDPR.
 *
 * The clock is frozen on Tuesday 2026-11-10; weeks start on Monday (UTC),
 * so this week is "2026-11-09".
 */
class KudosTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const NOW = '2026-11-10 12:00:00';
    private const WEEK = '2026-11-09';

    private const AVA = 3;
    private const BO = 4;
    private const CY = 5;
    private const DEE = 6;
    private const ELI = 7;
    private const HAL = 8;   // unconfirmed email
    private const MO = 9;    // moderator
    private const VIP = 10;  // in a group with more kudos

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::NOW));

        $this->extension('flarum-gdpr', 'ernestdefoe-kudos');

        $user = fn (int $id, string $name, array $extra = []) => $extra + [
            'id' => $id, 'username' => $name, 'email' => "$name@machine.local", 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDays(30),
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                $user(self::AVA, 'ava'), $user(self::BO, 'bo'), $user(self::CY, 'cy'), $user(self::DEE, 'dee'), $user(self::ELI, 'eli'),
                $user(self::HAL, 'hal', ['is_email_confirmed' => 0]), $user(self::MO, 'mo'), $user(self::VIP, 'vip'),
            ],
            Group::class => [
                ['id' => 5, 'name_singular' => 'Helper', 'name_plural' => 'Helpers', 'is_hidden' => 0],
                ['id' => 6, 'name_singular' => 'Star', 'name_plural' => 'Stars', 'is_hidden' => 0],
            ],
            'group_user' => [
                ...array_map(fn ($id) => ['user_id' => $id, 'group_id' => Group::MEMBER_ID], [self::AVA, self::BO, self::CY, self::DEE, self::ELI, self::HAL, self::VIP]),
                ['user_id' => self::MO, 'group_id' => Group::MODERATOR_ID],
                ['user_id' => self::VIP, 'group_id' => 5],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Migrating to Flarum 2', 'slug' => 'migrating', 'user_id' => self::AVA, 'created_at' => Carbon::now()->subDay(), 'first_post_id' => 1, 'comment_count' => 2, 'last_post_number' => 2, 'is_private' => 0],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => self::AVA, 'type' => 'comment', 'number' => 1, 'content' => '<t><p>How do I migrate?</p></t>', 'created_at' => Carbon::now()->subDay(), 'is_private' => 0],
                ['id' => 2, 'discussion_id' => 1, 'user_id' => self::BO, 'type' => 'comment', 'number' => 2, 'content' => '<t><p>Like this.</p></t>', 'created_at' => Carbon::now()->subHours(20), 'is_private' => 0],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{int, array<string, mixed>|null} */
    private function api(string $method, string $path, ?int $as, array $body = []): array
    {
        $options = $as ? ['authenticatedAs' => $as] : [];
        if ($method !== 'GET') {
            $options['json'] = $body;
        }
        [$path, $query] = array_pad(explode('?', $path, 2), 2, '');
        parse_str($query, $params);
        $response = $this->send($this->request($method, '/api/kudos'.$path, $options)->withQueryParams($params));

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    /** @return array{int, array<string, mixed>|null} */
    private function give(int $from, int $to, array $extra = []): array
    {
        return $this->api('POST', '', $from, $extra + ['receiver' => $to, 'note' => 'Thanks for walking me through the migration!', 'style' => 'meadow']);
    }

    private function giveOk(int $from, int $to, array $extra = []): int
    {
        [$status, $body] = $this->give($from, $to, $extra);
        $this->assertSame(201, $status, json_encode($body));

        return (int) $body['card']['id'];
    }

    private function refusal(array $result): string
    {
        return (string) ($result[1]['errors'][0]['code'] ?? 'none:'.$result[0]);
    }

    private function set(string $key, mixed $value): void
    {
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('ernestdefoe-kudos.'.$key, $value);
    }

    private function notifications(int $userId, string $type): int
    {
        return $this->database()->table('notifications')->where('user_id', $userId)->where('type', $type)->count();
    }

    // ---------------------------------------------------------------- giving

    #[Test]
    public function a_member_gives_kudos_and_the_receiver_is_told(): void
    {
        $id = $this->giveOk(self::AVA, self::BO, ['post' => 2]);

        $kudos = Kudos::query()->findOrFail($id);
        $this->assertSame(self::WEEK, $kudos->weekKey());
        $this->assertSame('approved', $kudos->status);
        $this->assertSame(2, $kudos->post_id);
        $this->assertSame(1, $this->notifications(self::BO, 'kudosReceived'));

        [$status, $wall] = $this->api('GET', '', self::CY);
        $this->assertSame(200, $status);
        $this->assertCount(1, $wall['cards']);
        $this->assertSame('bo', $wall['cards'][0]['receiver']['username']);
        $this->assertSame('Migrating to Flarum 2', $wall['cards'][0]['post']['title']);
        $this->assertSame(1, $wall['week']['total']);

        // The receiver's alert list renders (the subject has a resource).
        $response = $this->send($this->request('GET', '/api/notifications', ['authenticatedAs' => self::BO]));
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertStringContainsString('walking me through', (string) $response->getBody());
    }

    #[Test]
    public function you_cannot_kudos_yourself(): void
    {
        $this->assertSame('kudos_self', $this->refusal($this->give(self::AVA, self::AVA)));
    }

    #[Test]
    public function the_same_person_at_most_once_a_week(): void
    {
        $this->giveOk(self::AVA, self::BO);
        $this->assertSame('kudos_already_this_week', $this->refusal($this->give(self::AVA, self::BO)));

        // Next week, again.
        Carbon::setTestNow(Carbon::parse(self::NOW)->addWeek());
        $this->giveOk(self::AVA, self::BO);
    }

    #[Test]
    public function a_few_a_week_and_they_dont_roll_over(): void
    {
        $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::AVA, self::CY);
        $this->giveOk(self::AVA, self::DEE);
        $this->assertSame('kudos_none_left', $this->refusal($this->give(self::AVA, self::ELI)));

        // Sunday night is still this week; Monday is a new one with 3, not 6.
        Carbon::setTestNow(Carbon::parse('2026-11-15 23:59:00'));
        $this->assertSame('kudos_none_left', $this->refusal($this->give(self::AVA, self::ELI)));
        Carbon::setTestNow(Carbon::parse('2026-11-16 00:00:01'));
        [, $me] = $this->api('GET', '/me', self::AVA);
        $this->assertSame(['allowance' => 3, 'used' => 0, 'left' => 3], array_intersect_key($me['me'], ['allowance' => 0, 'used' => 0, 'left' => 0]));
    }

    #[Test]
    public function the_week_follows_the_start_day_and_timezone(): void
    {
        // Sunday-start weeks in New York: Tuesday noon UTC is in the week of Sunday the 8th.
        $this->set('start_day', 0);
        $this->set('timezone', 'America/New_York');
        $id = $this->giveOk(self::AVA, self::BO);
        $this->assertSame('2026-11-08', Kudos::query()->findOrFail($id)->weekKey());
    }

    #[Test]
    public function the_week_follows_the_forums_timezone_unless_kudos_has_its_own(): void
    {
        // The forum's timezone (Festoon's setting) on a fresh install: Monday 03:00 UTC is still Sunday in Chicago.
        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)->set('ernestdefoe-festoon.timezone', 'America/Chicago');
        Carbon::setTestNow(Carbon::parse('2026-11-16 03:00:00'));
        $this->assertSame('2026-11-09', $this->api('GET', '', self::AVA)[1]['week']['key']);

        // Kudos's own setting wins, from the next week on.
        $this->set('timezone', 'Asia/Tokyo');
        $this->assertSame('2026-11-09', $this->api('GET', '', self::AVA)[1]['week']['key']);
        Carbon::setTestNow(Carbon::parse('2026-11-16 07:00:00'));
        $this->assertSame('2026-11-16', $this->api('GET', '', self::AVA)[1]['week']['key']);
    }

    #[Test]
    public function a_changed_week_rule_waits_for_the_week_to_end(): void
    {
        $this->giveOk(self::AVA, self::BO);

        // Tuesday: the admin moves the week to Sunday, Chicago time.
        $this->set('start_day', 0);
        $this->set('timezone', 'America/Chicago');

        // This week carries on unchanged: same key, the allowance already used, bo already thanked, the card on the wall.
        [, $wall] = $this->api('GET', '', self::AVA);
        $this->assertSame(self::WEEK, $wall['week']['key']);
        $this->assertCount(1, $wall['cards']);
        $this->assertSame(1, $wall['me']['used']);
        $this->assertSame('kudos_already_this_week', $this->refusal($this->give(self::AVA, self::BO)));

        // The old week ends Monday 00:00 UTC; from then on the new rule: Sunday the 15th, Chicago.
        Carbon::setTestNow(Carbon::parse('2026-11-15 23:30:00'));
        $this->assertSame(self::WEEK, $this->api('GET', '', self::AVA)[1]['week']['key']);
        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00:00'));
        [, $wall] = $this->api('GET', '', self::AVA);
        $this->assertSame('2026-11-15', $wall['week']['key']);
        $this->assertSame([self::WEEK], array_column($wall['archive'], 'week'));
        $this->giveOk(self::AVA, self::BO);

        // The old week is judged, by its stored key.
        $made = $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertSame(self::WEEK, $made?->weekKey());
    }

    #[Test]
    public function only_established_givers_count_toward_the_spotlight_and_ineligible_winners_are_skipped(): void
    {
        $fresh = fn (int $id) => ['id' => $id, 'username' => "new$id", 'email' => "new$id@machine.local", 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDay(), 'password' => 'x'];
        $this->database()->table('users')->insert([$fresh(20), $fresh(21), $fresh(22)]);
        $this->database()->table('group_user')->insert(array_map(fn ($id) => ['user_id' => $id, 'group_id' => Group::MEMBER_ID], [20, 21, 22]));

        // Three day-old accounts thank cy; one established member thanks bo.
        foreach ([20, 21, 22] as $id) {
            $this->giveOk($id, self::CY);
        }
        $this->giveOk(self::AVA, self::BO);

        // Unconfirmed members can't be thanked at all.
        $this->assertSame('kudos_receiver_unavailable', $this->refusal($this->give(self::AVA, self::HAL)));
        // ...and one who got kudos before losing confirmation is skipped as a winner.
        $this->database()->table('kudos')->insert([
            ['giver_id' => self::DEE, 'receiver_id' => self::ELI, 'week' => self::WEEK, 'note' => 'Thanks!', 'style' => 'tide', 'status' => 'approved', 'created_at' => Carbon::now()],
            ['giver_id' => self::MO, 'receiver_id' => self::ELI, 'week' => self::WEEK, 'note' => 'Thanks!', 'style' => 'tide', 'status' => 'approved', 'created_at' => Carbon::now()],
        ]);
        $this->database()->table('users')->where('id', self::ELI)->update(['is_email_confirmed' => 0]);

        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));
        $made = $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertSame(self::BO, $made?->user_id);
        $this->assertSame(1, $made?->count);
    }

    #[Test]
    public function with_the_queue_on_the_spotlight_waits_for_late_approvals(): void
    {
        $this->set('moderation', '1');
        Carbon::setTestNow(Carbon::parse('2026-11-15 23:00'));
        $id = $this->giveOk(self::AVA, self::BO);

        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));
        $this->assertNull($this->app()->getContainer()->make(Spotlights::class)->run());

        Carbon::setTestNow(Carbon::parse('2026-11-16 09:00'));
        $this->assertSame(200, $this->api('PATCH', "/$id", self::MO, ['action' => 'approve'])[0]);

        Carbon::setTestNow(Carbon::parse('2026-11-17 01:00'));
        $this->assertSame(self::BO, $this->app()->getContainer()->make(Spotlights::class)->run()?->user_id);
    }

    #[Test]
    public function a_failed_reward_run_still_records_the_group_it_joined(): void
    {
        $this->set('spotlight_group', 6);
        $this->giveOk(self::AVA, self::BO);
        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));

        $container = $this->app()->getContainer();
        $real = $container->make(\Flarum\Notification\NotificationSyncer::class);
        $failing = $this->createStub(\Flarum\Notification\NotificationSyncer::class);
        $failing->method('sync')->willThrowException(new \RuntimeException('mail server down'));
        $container->instance(\Flarum\Notification\NotificationSyncer::class, $failing);
        try {
            $container->make(Spotlights::class)->run();
            $this->fail('the reward run should have failed');
        } catch (\RuntimeException) {
        }
        $spot = Spotlight::query()->firstOrFail();
        $this->assertSame(6, (int) $spot->group_id);
        $this->assertNull($spot->rewarded_at);

        // The next run finishes the rewards; a week later the group is taken back.
        $container->instance(\Flarum\Notification\NotificationSyncer::class, $real);
        $container->make(Spotlights::class)->run();
        $this->assertNotNull(Spotlight::query()->firstOrFail()->rewarded_at);
        Carbon::setTestNow(Carbon::parse('2026-11-23 01:00'));
        $container->make(Spotlights::class)->run();
        $this->assertFalse($this->database()->table('group_user')->where(['user_id' => self::BO, 'group_id' => 6])->exists());
    }

    #[Test]
    public function groups_can_get_more(): void
    {
        $this->set('group_allowances', json_encode(['5' => 5]));
        foreach ([self::AVA, self::BO, self::CY, self::DEE, self::ELI] as $to) {
            $this->giveOk(self::VIP, $to);
        }
        $this->assertSame('kudos_none_left', $this->refusal($this->give(self::VIP, self::MO)));

        // A bad saved value can't hand out unlimited kudos.
        $this->set('allowance', '100000');
        [, $me] = $this->api('GET', '/me', self::AVA);
        $this->assertSame(50, $me['me']['allowance']);
    }

    #[Test]
    public function a_deleted_kudos_gives_the_kudos_back(): void
    {
        $id = $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::AVA, self::CY);
        $this->giveOk(self::AVA, self::DEE);

        [$status] = $this->api('DELETE', "/$id", self::MO);
        $this->assertSame(204, $status);
        $this->assertSame(0, $this->notifications(self::BO, 'kudosReceived'));
        $this->giveOk(self::AVA, self::ELI);
    }

    #[Test]
    public function the_note_is_checked_and_cleaned(): void
    {
        $this->assertSame('kudos_note_short', $this->refusal($this->give(self::AVA, self::BO, ['note' => '  hi '])));
        $this->assertSame('kudos_note_long', $this->refusal($this->give(self::AVA, self::BO, ['note' => str_repeat('a', 281)])));

        $id = $this->giveOk(self::AVA, self::BO, ['note' => "Thank\u{202E} you\n\n  so   much 👨‍👩‍👧", 'style' => 'not-a-style']);
        $kudos = Kudos::query()->findOrFail($id);
        $this->assertSame('Thank you so much 👨‍👩‍👧', $kudos->note);
        $this->assertSame('sunrise', $kudos->style);
    }

    #[Test]
    public function the_post_must_be_the_receivers_and_readable(): void
    {
        // ava's own post, given to bo.
        $this->assertSame('kudos_bad_post', $this->refusal($this->give(self::AVA, self::BO, ['post' => 1])));
        $this->assertSame('kudos_bad_post', $this->refusal($this->give(self::AVA, self::BO, ['post' => 999])));

        $this->database()->table('posts')->where('id', 2)->update(['hidden_at' => Carbon::now()]);
        $this->assertSame('kudos_bad_post', $this->refusal($this->give(self::CY, self::BO, ['post' => 2])));
    }

    #[Test]
    public function guests_and_unconfirmed_members_cannot_give(): void
    {
        // A guest never gets as far as a kudos (the CSRF check or the permission turns them away).
        $this->assertContains($this->api('POST', '', null, ['receiver' => self::BO, 'note' => 'Thanks a lot!'])[0], [400, 403]);
        $this->assertSame(0, Kudos::query()->count());
        $this->assertSame('kudos_confirm_email', $this->refusal($this->give(self::HAL, self::BO)));
        $this->assertSame('kudos_no_receiver', $this->refusal($this->give(self::AVA, 999)));
    }

    // ---------------------------------------------------------------- privacy

    #[Test]
    public function a_forum_closed_to_guests_shows_guests_nothing(): void
    {
        $this->giveOk(self::AVA, self::BO);
        $this->database()->table('group_permission')->where('group_id', Group::GUEST_ID)->where('permission', 'viewForum')->delete();

        $this->assertSame(403, $this->api('GET', '', null)[0]);
        $this->assertSame(403, $this->api('GET', '/users/'.self::BO, null)[0]);

        $response = $this->send($this->request('GET', '/api'));
        $forum = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertFalse($forum['kudosCanView']);
        $this->assertNull($forum['kudosSpotlight']);

        // Members still see it.
        $this->assertSame(200, $this->api('GET', '', self::CY)[0]);
    }

    #[Test]
    public function a_hidden_note_is_gone_for_everyone_but_moderators(): void
    {
        $id = $this->giveOk(self::AVA, self::BO);
        $this->assertSame(403, $this->api('PATCH', "/$id", self::CY, ['action' => 'hide_note'])[0]);
        $this->assertSame(200, $this->api('PATCH', "/$id", self::MO, ['action' => 'hide_note'])[0]);

        [, $wall] = $this->api('GET', '', null);
        $this->assertNull($wall['cards'][0]['note']);
        $this->assertTrue($wall['cards'][0]['noteHidden']);
        [, $profile] = $this->api('GET', '/users/'.self::BO, self::BO);
        $this->assertNull($profile['cards'][0]['note']);
        $response = $this->send($this->request('GET', '/api/notifications', ['authenticatedAs' => self::BO]));
        $this->assertStringNotContainsString('walking me through', (string) $response->getBody());

        [, $wall] = $this->api('GET', '', self::MO);
        $this->assertSame('Thanks for walking me through the migration!', $wall['cards'][0]['note']);
    }

    #[Test]
    public function the_queue_holds_kudos_until_a_moderator_approves(): void
    {
        $this->set('moderation', '1');
        $id = $this->giveOk(self::AVA, self::BO);
        $this->assertSame(0, $this->notifications(self::BO, 'kudosReceived'));

        // Nobody else sees it; the giver does, and it already counts against their week.
        $this->assertCount(0, $this->api('GET', '', self::BO)[1]['cards']);
        $this->assertCount(0, $this->api('GET', '', null)[1]['cards']);
        $this->assertTrue($this->api('GET', '', self::AVA)[1]['cards'][0]['pending']);
        $this->assertSame(403, $this->api('GET', '?queue=1', self::CY)[0]);
        [, $queue] = $this->api('GET', '?queue=1', self::MO);
        $this->assertSame($id, $queue['cards'][0]['id']);

        $this->assertSame(200, $this->api('PATCH', "/$id", self::MO, ['action' => 'approve'])[0]);
        $this->assertSame(200, $this->api('PATCH', "/$id", self::MO, ['action' => 'approve'])[0]);
        $this->assertSame(1, $this->notifications(self::BO, 'kudosReceived'));
        $this->assertCount(1, $this->api('GET', '', self::BO)[1]['cards']);

        // A moderator's own kudos skip the queue; a giver can take back a pending one.
        $this->assertSame('approved', Kudos::query()->findOrFail($this->giveOk(self::MO, self::BO))->status);
        $pending = $this->giveOk(self::CY, self::BO);
        // Someone else's pending kudos doesn't exist for bo at all.
        $this->assertSame(404, $this->api('DELETE', "/$pending", self::BO)[0]);
        $this->assertSame(204, $this->api('DELETE', "/$pending", self::CY)[0]);
    }

    #[Test]
    public function a_profile_shows_received_kudos_and_top_givers(): void
    {
        $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::CY, self::BO);
        Carbon::setTestNow(Carbon::parse(self::NOW)->addWeek());
        $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::BO, self::AVA);

        [$status, $p] = $this->api('GET', '/users/'.self::BO, null);
        $this->assertSame(200, $status);
        $this->assertSame(3, $p['received']);
        $this->assertSame(1, $p['given']);
        $this->assertSame(1, $p['thisWeek']);
        $this->assertSame('ava', $p['topGivers'][0]['user']['username']);
        $this->assertSame(2, $p['topGivers'][0]['count']);
        $this->assertCount(3, $p['cards']);
    }

    // ---------------------------------------------------------------- spotlight

    #[Test]
    public function the_spotlight_is_set_once_with_ties_to_whoever_got_there_first(): void
    {
        // bo reaches 2 first, cy reaches 2 later.
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00'));
        $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::DEE, self::BO);
        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00'));
        $this->giveOk(self::AVA, self::CY);
        $this->giveOk(self::DEE, self::CY);

        // Not before the week ends.
        $this->assertNull($this->app()->getContainer()->make(Spotlights::class)->run());

        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));
        $spotlights = $this->app()->getContainer()->make(Spotlights::class);
        $made = $spotlights->run();
        $this->assertSame(self::BO, $made?->user_id);
        $this->assertNull($spotlights->run());
        $this->assertSame(1, Spotlight::query()->count());
        $this->assertSame(1, $this->notifications(self::BO, 'kudosSpotlight'));

        // The banner, for everyone who can see bo; the wall shows it with this week.
        $forum = json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];
        $this->assertSame('bo', $forum['kudosSpotlight']['user']['username']);
        $this->assertSame(2, $forum['kudosSpotlight']['count']);
        $this->assertSame(self::WEEK, $this->api('GET', '', null)[1]['spotlight']['week']);

        // The week after next, the banner is gone.
        Carbon::setTestNow(Carbon::parse('2026-11-23 01:00'));
        $forum = json_decode((string) $this->send($this->request('GET', '/api'))->getBody(), true)['data']['attributes'];
        $this->assertNull($forum['kudosSpotlight']);
    }

    #[Test]
    public function the_spotlight_group_is_worn_for_a_week(): void
    {
        $this->set('spotlight_group', 6);
        $this->giveOk(self::AVA, self::BO);
        $this->database()->table('group_user')->insert(['user_id' => self::CY, 'group_id' => 6]);

        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));
        $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertTrue($this->database()->table('group_user')->where(['user_id' => self::BO, 'group_id' => 6])->exists());

        // Next week cy (already a Star) wins: bo leaves the group, cy stays in it after.
        $this->giveOk(self::AVA, self::CY);
        Carbon::setTestNow(Carbon::parse('2026-11-23 01:00'));
        $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertFalse($this->database()->table('group_user')->where(['user_id' => self::BO, 'group_id' => 6])->exists());

        Carbon::setTestNow(Carbon::parse('2026-11-30 01:00'));
        $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertTrue($this->database()->table('group_user')->where(['user_id' => self::CY, 'group_id' => 6])->exists());

        // A built-in group is never used.
        $this->set('spotlight_group', Group::ADMINISTRATOR_ID);
        Carbon::setTestNow(Carbon::parse('2026-11-30 09:00'));
        $this->giveOk(self::AVA, self::DEE);
        Carbon::setTestNow(Carbon::parse('2026-12-07 01:00'));
        $this->app()->getContainer()->make(Spotlights::class)->run();
        $this->assertFalse($this->database()->table('group_user')->where(['user_id' => self::DEE, 'group_id' => Group::ADMINISTRATOR_ID])->exists());
    }

    // ---------------------------------------------------------------- gdpr

    #[Test]
    public function gdpr_export_and_erase(): void
    {
        $this->giveOk(self::AVA, self::BO);
        $this->giveOk(self::BO, self::AVA);
        $this->giveOk(self::CY, self::AVA);

        $user = User::query()->findOrFail(self::AVA);
        $data = $this->app()->getContainer()->make(KudosData::class, ['user' => $user, 'erasureRequest' => null]);
        $export = $data->export();
        $json = json_decode(array_values($export[0])[0], true);
        $this->assertSame('bo', $json['given'][0]['to']);
        $this->assertCount(2, $json['received']);

        $data->anonymize();
        $this->assertNull(Kudos::query()->where('receiver_id', self::BO)->first()->giver_id);
        $this->assertSame(0, Kudos::query()->where('receiver_id', self::AVA)->count());

        $this->app()->getContainer()->make(KudosData::class, ['user' => User::query()->findOrFail(self::BO), 'erasureRequest' => null])->delete();
        $this->assertSame(0, Kudos::query()->where('receiver_id', self::BO)->orWhere('giver_id', self::BO)->count());
    }
}
