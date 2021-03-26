<?php

namespace App\Http\Controllers;

use App\Models\TwitchUser;
use App\Twitch\TwitchApi;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class TwitchAuthController extends Controller
{
    protected $twitchApi;

    public function __construct()
    {
        $this->twitchApi = new TwitchApi;
    }

    public function index()
    {
        $clientId = config('services.twitch.client_id');
        $redirectUri = config('services.twitch.redirect_uri');
        $responseType = 'code';
        $scopes = implode(' ', [
            'user:read:email',
            'user_read',
            'user:read:subscriptions',
            'user:edit',
            'user_subscriptions',
        ]);
        $state = sha1(random_int(11111111, 99999999));

        // Store the state in cache, for pulling later when we get the callback
        Cache::add($state, true, now()->addMinutes(10));

        $url = "https://id.twitch.tv/oauth2/authorize?client_id={$clientId}&redirect_uri={$redirectUri}&response_type={$responseType}&scope={$scopes}&state={$state}";

        return view('twitch_auth_index', [
            'url' => $url,
        ]);
    }

    public function callback()
    {
        $authCode = request('code');

        if (! $authCode) {
            $message = 'Return code not set! :(';
            return view('twitch_auth_callback', ['message' => $message]);
        }

        $scopes = request('scope');
        $state = request('state');

        if (! Cache::has($state)) {
            $message = 'State code mismatch!';
            return view('twitch_auth_callback', ['message' => $message]);
        }

        // Remove state so it can't be re-used
        Cache::forget($state);

        // Use the given auth code to request an access token
        $response = $this->twitchApi->requestOauthToken($authCode);

        if (! Arr::has($response, 'access_token')) {
            $message = 'Access token not returned :(';
            return view('twitch_auth_callback', ['message' => $message]);
        }

        $authInfo = $response;

        $userInfo = $this->twitchApi->getUserInfo($authInfo['access_token']);

        $user = TwitchUser::updateOrCreate([
            'twitch_user_id' => $userInfo['_id'],
        ], [
            'display_name' => $userInfo['display_name'],
            'login' => $userInfo['name'],
            'refresh_token' => $authInfo['refresh_token'],
            'access_token' => $authInfo['access_token'],
            'scopes' => $authInfo['scope'],
            'token_type' => $authInfo['token_type'],
        ]);

        return view('twitch_auth_callback', ['data' => $userInfo]);
    }

    public function refresh($twitchUserId)
    {
        $twitchUser = TwitchUser::where('twitch_user_id', $twitchUserId)->first();

        return $this->twitchApi->refreshOauthToken($twitchUser);
    }

    public function validateToken($twitchUserId)
    {
        $twitchUser = TwitchUser::where('twitch_user_id', $twitchUserId)->first();

        return $this->twitchApi->validateToken($twitchUser);
    }
}
