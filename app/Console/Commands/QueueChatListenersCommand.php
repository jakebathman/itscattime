<?php

namespace App\Console\Commands;

use App\Jobs\ListenToChat;
use Illuminate\Console\Command;

class QueueChatListenersCommand extends Command
{
    protected $signature = 'chat:start';

    protected $description = "Send all channels' chat listeners to the queue";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $channels = config('channels');

        foreach ($channels as $channel) {
            $this->line('Dispatching listener for ' . $channel);
            ListenToChat::dispatch($channel);
        }

        return 0;
    }
}
