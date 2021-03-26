<?php

namespace App\Providers;

use App\Twitch\Socket\IrcSocket;
use App\Twitch\Socket\SocketContract;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(SocketContract::class, IrcSocket::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
