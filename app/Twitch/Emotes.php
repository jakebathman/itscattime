<?php

namespace App\Twitch;

use Illuminate\Support\Arr;

class Emotes
{
    public static function getRandomCats($emoteOnly = false, $amount = 5)
    {
        $emotes = collect(config('emotes'))->map(function ($emoteGroup) use ($emoteOnly) {
            if ($emoteOnly) {
                // Only return groups that can be used under emote only mode
                if ($emoteGroup['emote_only'] === true) {
                    return $emoteGroup['emotes'];
                }
            } else {
                return $emoteGroup['emotes'];
            }
        })
        ->filter()
        ->flatten()
        ->toArray();

        return implode(
            ' ',
            Arr::random($emotes, $amount)
        );
    }

    public function getAvailableEmotes()
    {
        // Call the Twitch API and see what emotes are available
    }
}
