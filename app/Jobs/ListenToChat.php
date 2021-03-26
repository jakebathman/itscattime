<?php

namespace App\Jobs;

use App\Twitch\Emotes;
use App\Twitch\IrcMessage;
use App\Twitch\Socket\SocketContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ListenToChat implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    protected static $host = 'irc.chat.twitch.tv';
    protected static $port = '6667';

    protected $sendMessages = true;

    protected $client;
    protected $channel;
    protected $nickname;
    protected $token;
    protected $rateLimitTimes = 3;
    protected $rateLimitSeconds = 10;

    public function __construct(string $channel)
    {
        $this->token = config('services.twitch.irc_token');
        $this->nickname = config('services.twitch.nickname');

        $this->channel = $channel;

        $this->client = app(SocketContract::class);
    }

    public function handle()
    {
        $this->connect();

        if (! $this->client->isConnected()) {
            Log::error($this->t() . 'Connection failed :(');

            return 1;
        }

        Log::info($this->t() . 'Connected!');
        Log::channel('slack')->notice('Connected to ' . $this->channel);

        $firstConnect = true;
        $buffer = null;

        while (true) {
            $chunkBytes = 1024;
            // Log::info($this->t() . "Reading chat ({$chunkBytes} bytes at a time)");
            $data = $this->client->read($chunkBytes);

            // Check that the message ends in a newline (or else it was a partial)
            if (! Str::endsWith($data, "\n")) {
                // Store this bit and add the next chunk to make a full message
                // Log::info('Partial, adding to buffer: ' . $data);
                $buffer .= $data;
            } else {
                collect(explode("\n", trim($buffer . $data)))->each(function ($content) {
                    $this->processMessage($content);
                });

                $buffer = null;
            }
            usleep(100000); // 0.1 sec

            // Check for existence of an error, which could indicate
            // the socket didn't connect and won't be read
            if ($this->client->getLastError() !== 0) {
                // Try re-connecting again
                Log::error('ERROR!!!');
                sleep(1);
                Log::warning('Re-connecting');
                $this->connect();

                if (! $this->client->isConnected()) {
                    Log::error($this->t() . 'Connection failed :(');

                    return 1;
                }
            }
        }

        $this->client->close();

        Log::info($this->t() . 'Closed.');

        return 0;
    }

    public function connect()
    {
        $this->client->connect(self::$host, self::$port);

        $this->authenticate();
        $this->requestCommands();
        $this->requestTags();
        $this->setNick();
        $this->joinChannel();
    }

    public function processMessage($text)
    {
        $message = new IrcMessage($text);

        $this->heartbeat();

        switch ($message->type) {
            case IrcMessage::TYPE_MESSAGE:
                // Only log mod and my own messages
                if ($message->isMod() || in_array($message->username, ['jakebathman', 'itscattime', 'puptime'])) {
                    Log::info($text);
                    Log::info($this->t() . 'Type: ' . $message->type);
                }

                // This is a user message
                $this->processUserMessage($message);
                break;

            case IrcMessage::TYPE_PING:
                Log::info($text);
                Log::info($this->t() . 'Type: ' . $message->type);
                // PING from IRC, requires PONG
                $this->client->send(sprintf('PONG :%s', $message->message));
                break;

            default:
                Log::info($text);
                Log::info($this->t() . 'Type: ' . $message->type);
                break;
        }
    }

    public function processUserMessage(IrcMessage $message)
    {
        // Break this out into a matching function
        // Should always match some things (like a direct mention)
        // and be rate-limited on other things
        if (preg_match("/\b(meow|cat|kitten|kitty|cats|itscattime|cattime|cello|polo|pussycat|IT'S CAT TIME|gato)\b/i", $message)) {
            if ($message->username == 'itscattime') {
                // Don't respond to cat time, just in case
                return;
            }

            // Log this message, since it's triggering a response
            Log::info($message);
            Log::info($this->t() . 'Type: ' . $message->type);

            if (! $this->underRateLimit()) {
                // Don't respond, don't spam
                Log::notice('Over rate limit, not responding');

                return false;
            }

            // Log this response to redis for rate limiting
            $this->logToRedis($message);

            // Get a set of cat emotes
            $cats = $this->getCats();

            // Log to Slack
            $this->logToSlack($message, $cats);

            if (! $this->sendMessages) {
                Log::notice('Would have responded ' . $cats);
                return false;
            }

            Log::info('Responding ' . $cats);
            $this->sendMessage($cats);
        }
    }

    public function logToRedis(IrcMessage $message)
    {
        // Set a key in redis, with an expiration to prevent too many responses
        $messageHash = substr(sha1($message->tags['id']), 0, 8);

        Redis::setex("cattime:response:{$messageHash}", $this->rateLimitSeconds, 1);
    }

    public function logToSlack(IrcMessage $message, string $cats)
    {
        Log::channel('slack')->info(
            'Bot responded',
            [
                'Channel' => "`{$message->channel}`",
                'Trigger' => "`{$message->username}: {$message->message}`",
                'Response' => "`{$cats}`",
            ]
        );
    }

    public function heartbeat()
    {
        // Log this channel's connection heartbeat so we can tell roughly
        // whether it's connected from outside of this job
        Redis::setex("cattime:connections:{$this->channel}", (10 * 60), 1);
    }

    public function underRateLimit()
    {
        $keys = Redis::keys('cattime:*');
        if (count($keys) >= $this->rateLimitTimes) {
            // Log to Slack
            Log::channel('slack')->warning('Over rate limit, not responding', ['Key count' => count($keys)]);
            return false;
        }

        return true;
    }

    public function getCats($emoteOnly = false, $amount = 5)
    {
        return Emotes::getRandomCats($emoteOnly, $amount);
    }

    public function authenticate()
    {
        $this->client->send(sprintf('PASS %s', $this->token));
    }

    public function setNick()
    {
        Log::info($this->t() . 'Setting nick: ' . $this->nickname);
        $this->client->send(sprintf('NICK %s', $this->nickname));
    }

    public function joinChannel()
    {
        Log::info($this->t() . 'Joining channel: ' . sprintf('JOIN #%s', $this->channel));
        $this->client->send(sprintf('JOIN #%s', $this->channel));
    }

    public function requestTags()
    {
        $this->client->send('CAP REQ :twitch.tv/tags');
    }

    public function requestCommands()
    {
        $this->client->send('CAP REQ :twitch.tv/commands');
    }

    public function sendMessage($message)
    {
        Log::info($this->t() . 'Sending ' . $message . "\n");

        $this->client->send(sprintf('PRIVMSG #%s :%s', $this->channel, $message));
    }

    public function t()
    {
        $t = now()->format('Y-m-d H:i:s');

        return "[{$t}] ";
    }

    public function uniqueId()
    {
        return $this->channel;
    }
}
