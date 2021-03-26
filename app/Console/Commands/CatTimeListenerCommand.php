<?php

namespace App\Console\Commands;

use App\Twitch\Emotes;
use App\Twitch\IrcMessage;
use App\Twitch\Socket\SocketContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class CatTimeListenerCommand extends Command
{
    protected static $host = 'irc.chat.twitch.tv';
    protected static $port = '6667';

    protected $signature = 'chat {channel=jakebathman}';
    protected $description = 'Start IRC listener.';

    protected $sendMessages = true;

    protected $client;
    protected $channel;
    protected $nickname;
    protected $token;
    protected $rateLimitTimes = 3;
    protected $rateLimitSeconds = 10;

    public function __construct()
    {
        parent::__construct();

        $this->token = config('services.twitch.irc_token');
        $this->nickname = config('services.twitch.nickname');

        $this->client = app(SocketContract::class);
    }

    public function handle()
    {
        $this->channel = Str::lower(trim($this->argument('channel')));

        $this->connect();

        if (! $this->client->isConnected()) {
            $this->error($this->t() . 'Connection failed :(');

            return 1;
        }

        $this->comment($this->t() . 'Connected!');

        $firstConnect = true;
        $buffer = null;

        while (true) {
            $chunkBytes = 1024;
            // $this->comment($this->t() . "Reading chat ({$chunkBytes} bytes at a time)");
            $data = $this->client->read($chunkBytes);

            // Check that the message ends in a newline (or else it was a partial)
            if (! Str::endsWith($data, "\n")) {
                // Store this bit and add the next chunk to make a full message
                // $this->question('Partial, adding to buffer: ' . $data);
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
                $this->error('ERROR!!!');
                sleep(1);
                $this->error('Re-connecting');
                $this->connect();

                if (! $this->client->isConnected()) {
                    $this->error($this->t() . 'Connection failed :(');

                    return 1;
                }
            }
        }

        $this->client->close();

        $this->comment($this->t() . 'Closed.');

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

        switch ($message->type) {
            case IrcMessage::TYPE_MESSAGE:
                // Only log mod and my own messages
                if ($message->isMod() || in_array($message->username, ['jakebathman', 'itscattime', 'puptime'])) {
                    $this->line($text);
                    $this->line($this->t() . 'Type: ' . $message->type);
                }

                // This is a user message
                $this->processUserMessage($message);
                break;

            case IrcMessage::TYPE_PING:
                $this->question($text);
                $this->question($this->t() . 'Type: ' . $message->type);
                // PING from IRC, requires PONG
                $this->client->send(sprintf('PONG :%s', $message->message));
                break;

            default:
                $this->info($text);
                $this->info($this->t() . 'Type: ' . $message->type);
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
            $this->line($message);
            $this->line($this->t() . 'Type: ' . $message->type);

            if (! $this->underRateLimit()) {
                // Don't respond, don't spam
                $this->error('Over rate limit, not responding');

                return false;
            }

            // Log this response to redis for rate limiting
            $this->logToRedis($message);

            // Get a set of cat emotes
            $cats = $this->getCats();

            // Log to Slack
            $this->logToSlack($message, $cats);

            if (! $this->sendMessages) {
                $this->error('Would have responded ' . $cats);
                return false;
            }

            $this->error('Responding ' . $cats);
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
        $this->comment($this->t() . 'Setting nick: ' . $this->nickname);
        $this->client->send(sprintf('NICK %s', $this->nickname));
    }

    public function joinChannel()
    {
        $this->comment($this->t() . 'Joining channel: ' . sprintf('JOIN #%s', $this->channel));
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
        $this->comment($this->t() . 'Sending ' . $message . "\n");

        $this->client->send(sprintf('PRIVMSG #%s :%s', $this->channel, $message));
    }

    public function t()
    {
        $t = now()->format('Y-m-d H:i:s');

        return "[{$t}] ";
    }
}
