<?php

namespace ErnestDefoe\Kudos\Console;

use Carbon\Carbon;
use ErnestDefoe\Kudos\Settings;
use ErnestDefoe\Kudos\Spotlights;
use Flarum\Console\AbstractCommand;

/**
 * Hourly (the scheduler): once a week has ended, its most-thanked member
 * goes in the spotlight. Safe to run any number of times (Spotlights).
 */
class SpotlightCommand extends AbstractCommand
{
    public function __construct(
        private readonly Spotlights $spotlights,
        private readonly Settings $settings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('kudos:spotlight')
            ->setDescription('Put last week\'s most-thanked member in the Kudos spotlight (once per week).');
    }

    protected function fire(): int
    {
        $this->settings->stampRun(Carbon::now()->getTimestamp());
        $made = $this->spotlights->run();

        $this->info($made ? "Spotlight for the week of {$made->weekKey()}: member {$made->user_id} with {$made->count}." : 'Nothing new to spotlight.');

        return 0;
    }
}
