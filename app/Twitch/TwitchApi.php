<?php

namespace App\Twitch;

use App\Models\TwitchUser;
use Illuminate\Support\Facades\Http;

class TwitchApi
{
    protected $clientId;
    protected $clientSecret;
    protected $redirectUri;

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->clientSecret = config('services.twitch.client_secret');
        $this->redirectUri = config('services.twitch.redirect_uri');
    }

    public function getUserEmotes(TwitchUser $twitchUser)
    {
        $url = '';
    }

    public function getUserInfo($accessToken)
    {
        $url = 'https://api.twitch.tv/kraken/user';

        return Http::withHeaders([
            'Accept' => 'application/vnd.twitchtv.v5+json',
            'Client-ID' => $this->clientId,
            'Authorization' => "OAuth {$accessToken}",
        ])
        ->get($url)
        ->json();
    }
    /**
     * After being redirected back from Twitch with an auth code,
     * we need to exchange that for an access token.
     */
    public function requestOauthToken(string $authCode)
    {
        $url = "https://id.twitch.tv/oauth2/token?client_id={$this->clientId}&client_secret={$this->clientSecret}&code={$authCode}&grant_type=authorization_code&redirect_uri={$this->redirectUri}";

        return Http::post($url)->json();
    }

    // Refresh and update the user access token
    public function refreshOauthToken(TwitchUser $twitchUser)
    {
        $refreshToken = urlencode($twitchUser->refresh_token);

        $url = "https://id.twitch.tv/oauth2/token?grant_type=refresh_token&refresh_token={$refreshToken}&client_id={$this->clientId}&client_secret={$this->clientSecret}";

        $response = Http::post($url)->json();

        $twitchUser->update([
            'refresh_token' => $response['refresh_token'],
            'access_token' => $response['access_token'],
            'scopes' => $response['scope'],
            'token_type' => $response['token_type'],
        ]);

        $twitchUser->save();

        return $twitchUser;
    }

    public function validateToken(TwitchUser $twitchUser)
    {
        $url = 'https://id.twitch.tv/oauth2/validate';

        $response = Http::withHeaders([
            'Authorization' => "OAuth {$twitchUser->access_token}",
        ])
        ->get($url)
        ->json();

        if ($response['client_id'] != $this->clientId) {
            return $this->refreshOauthToken($twitchUser);
        }

        return $response;
    }
}
