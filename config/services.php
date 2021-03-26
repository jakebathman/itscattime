<?php

return [

    'twitch' => [
        'irc_token' => env('TWITCH_IRC_TOKEN'),
        'nickname' => env('TWITCH_IRC_NICKNAME'),
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
        'redirect_uri' => env('TWITCH_REDIRECT_URI'),
    ],

];
