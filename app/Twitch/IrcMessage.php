<?php

namespace App\Twitch;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class IrcMessage
{
    const TYPE_PING = 'ping';
    const TYPE_JOIN = 'join';
    const TYPE_PART = 'part';
    const TYPE_MESSAGE = 'privmsg';
    const TYPE_UNKNOWN = 'unknown';

    const KNOWN_BOTS = ['nightbot', 'streamelements'];

    public $type;
    public $channel;
    public $username;
    public $message;
    public $tags;

    public array $badges = [];
    public array $emotes = [];
    public string $displayName;

    public function __construct($message)
    {
        $this->parse($message);
    }

    public function parse($message)
    {
        $this->type = self::TYPE_UNKNOWN;

        if ($this->isPing($message)) {
            $this->type = self::TYPE_PING;
        } elseif ($this->isJoin($message)) {
            $this->type = self::TYPE_JOIN;
        } elseif ($this->isPart($message)) {
            $this->type = self::TYPE_PART;
        } elseif ($this->isUserMessage($message)) {
            $this->type = self::TYPE_MESSAGE;
        }
    }

    public function isPing($message)
    {
        if (preg_match('/PING :(.*)/i', $message, $matches)) {
            $this->message = $matches[1];

            return true;
        }
    }

    public function isJoin($message)
    {
        if (preg_match('/:(\S+)!\S+@\S+ JOIN #(\S+)/i', $message, $matches)) {
            $this->username = $matches[1];
            $this->channel = $matches[2];

            return true;
        }
    }

    public function isPart($message)
    {
        if (preg_match('/:(\S+)!\S+@\S+ PART #(\S+)/i', $message, $matches)) {
            $this->username = $matches[1];
            $this->channel = $matches[2];

            return true;
        }
    }

    public function isUserMessage($message)
    {
        if (preg_match('/(?:@(.+?))?:(\S+)!\S+@\S+ PRIVMSG #(\S+) :(.*)/i', $message, $matches)) {
            $this->parseTags($matches[1]);
            $this->username = $matches[2];
            $this->channel = $matches[3];
            $this->message = $matches[4];

            return true;
        }
    }

    public function isBot()
    {
        return in_array($this->username, self::KNOWN_BOTS);
    }

    public function isMod()
    {
        return Arr::has($this->badges, 'moderator');
    }

    public function isSub()
    {
        return Arr::has($this->badges, 'subscriber');
    }

    public function isPartner()
    {
        return Arr::has($this->badges, 'partner');
    }

    public function isTwitchStaff()
    {
        return Arr::has($this->badges, 'staff');
    }

    public function isAdmin()
    {
        return Arr::has($this->badges, 'admin');
    }

    public function isBroadcaster()
    {
        return Arr::has($this->badges, 'broadcaster');
    }

    public function isVip()
    {
        return Arr::has($this->badges, 'vip');
    }

    public function isPrime()
    {
        return Arr::has($this->badges, 'premium');
    }

    public function parseTags($tagString)
    {
        $this->tags = [];
        $this->emotes = [];

        foreach (explode(';', $tagString) as $tag) {
            if (! Str::contains($tag, '=')) {
                $this->tags[$tag] = $tag;
                continue;
            }

            list($key, $value) = explode('=', $tag);
            $this->tags[$key] = $value;
        }

        // Get badge info
        $this->badges = $this->explodeToArray(
            Arr::get($this->tags, 'badges'),
            ',',
            '/'
        );

        // Get message emotes
        $this->emotes = $this->explodeToArray(
            Arr::get($this->tags, 'emotes'),
            '/',
            ':'
        );

        return $this->tags;
    }

    public function explodeToArray($stringItems, $itemDelim, $kvDelim)
    {
        $result = [];

        if ($stringItems) {
            foreach (explode($itemDelim, $stringItems) as $item) {
                if (! Str::contains($item, $kvDelim)) {
                    // No key/value, so just add item string to both
                    $result[$item] = $item;

                    continue;
                }

                list($key, $value) = explode($kvDelim, $item);
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function getEmotes()
    {
        return $this->emotes;
    }

    public function __toString()
    {
        return $this->message ?? '';
    }
}
