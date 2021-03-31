<?php

namespace App\Http\Controllers;

use App\Jobs\ListenToChat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ListenersController extends Controller
{
    public function index()
    {
        $channels = collect(config('channels'))->map(function ($channel) {
            $hasHeartbeat = Redis::get("cattime:connections:{$channel}");

            return [
                'name' => $channel,
                'status' => $hasHeartbeat ? 'connected' : 'not connected',
            ];
        });

        return view('listeners.index', [
            'channels' => $channels,
        ]);
    }

    public function start($channel)
    {
        Log::notice('Starting listener for ' . $channel);

        // ListenToChat::dispatch($channel);

        return redirect()->back();
    }

    public function restart()
    {
        Log::notice('Restarting listener');

        // Add terminate signal to redis
        Redis::set(config('listener.terminate_signal_key'), 1);

        // Sleep for a bit to prevent a race condition in the listener's handle() loop and allow for unload
        sleep(5);

        // See if there are any heartbeats, indicating it's reconnected
        if (empty(Redis::keys('cattime:connections:*'))) {
            // The command didn't restart right. Send message and try restarting.
            Log::channel('slack')->error("Listener didn't restart via supervisor!");
        }


        return redirect()->back();
    }
}
