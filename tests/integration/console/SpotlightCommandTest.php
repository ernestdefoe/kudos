<?php

namespace ErnestDefoe\Kudos\Tests\integration\console;

use Carbon\Carbon;
use Flarum\Testing\integration\ConsoleTestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/** kudos:spotlight is registered, judges last week once, and stamps the run for the admin page. */
class SpotlightCommandTest extends ConsoleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ernestdefoe-kudos');
        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'ava', 'email' => 'ava@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'bo', 'email' => 'bo@machine.local', 'is_email_confirmed' => 1],
            ],
            'kudos' => [
                ['id' => 1, 'giver_id' => 3, 'receiver_id' => 4, 'week' => '2026-11-09', 'note' => 'Thank you!', 'style' => 'tide', 'status' => 'approved', 'created_at' => '2026-11-10 10:00:00'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_spotlights_last_week_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-16 01:00'));
        $this->assertStringContainsString('Spotlight for the week of 2026-11-09: member 4 with 1', $this->runCommand(['command' => 'kudos:spotlight']));
        $this->assertStringContainsString('Nothing new', $this->runCommand(['command' => 'kudos:spotlight']));
        $this->assertNotNull($this->database()->table('settings')->where('key', 'ernestdefoe-kudos.last_run')->value('value'));
    }
}
