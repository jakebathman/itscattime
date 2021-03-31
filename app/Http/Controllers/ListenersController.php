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

        ListenToChat::dispatch($channel);

        return redirect()->back();
    }

    public function restart()
    {
        Log::notice('Restarting queue and all listeners');

        // Artisan::call('horizon:terminate');
        Artisan::call('chat:start');

        return redirect()->back();
    }
}
