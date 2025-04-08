<?php

namespace Shah\Guardian\Commands;

use Illuminate\Console\Command;

class GuardianCommand extends Command
{
    public $signature = 'guardian';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
