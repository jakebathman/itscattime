<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PrepareForListenerRestartCommand extends Command
{
    protected $signature = 'chat:prepare-restart';

    protected $description = 'Cleanup before restarting the listener command.';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        Log::notice('Preparing for listener restart');

        // Delete any heartbeat keys
        collect(Redis::keys('cattime:connections:*'))->each(function ($key) {
            Redis::del($key);
        });

        return 0;
    }
}
