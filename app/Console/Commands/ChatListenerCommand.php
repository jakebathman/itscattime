<?php

namespace App\Console\Commands;

use App\Twitch\Emotes;
use App\Twitch\IrcMessage;
use App\Twitch\Socket\SocketContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ChatListenerCommand extends Command
{
    protected static $host = 'irc.chat.twitch.tv';
    protected static $port = '6667';

    protected $signature = 'chat:start';
    protected $description = 'Start listening to configured channels.';

    protected $sendMessages = true;
    protected $listen = true;
    protected $terminateSignalKey;
    protected $unloaded = false;

    protected $client;
    protected $channels;
    protected $nickname;
    protected $token;
    protected $rateLimitTimes = 3;
    protected $rateLimitSeconds = 10;

    public function __construct()
    {
        parent::__construct();

        $this->token = config('services.twitch.irc_token');
        $this->nickname = config('services.twitch.nickname');
        $this->terminateSignalKey = config('listener.terminate_signal_key');
        $this->channels = config('channels');

        $this->client = app(SocketContract::class);
    }

    public function handle()
    {

        // Prepare to handle process kill signals gracefully
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGTERM, [$this, 'shutdown']);

        $this->connect();

        if (! $this->client->isConnected()) {
            Log::error($this->t() . 'Connection failed :(');

            return 1;
        }

        Log::info($this->t() . 'Connected!');
        Log::channel('slack')->notice('Connected to ' . implode(',', $this->channels));

        $firstConnect = true;
        $buffer = null;

        while ($this->listen) {
            Log::info('listen?', ['listen' => $this->listen]);
            // Before doing anything else, check for signal to stop listener in Redis
            $this->checkShouldTerminate();

            $chunkBytes = 1024;
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

        $this->unload();

        return 0;
    }

    public function shutdown()
    {
        return $this->listen = false;
    }

    public function unload()
    {
        $this->listen = false;

        if (! $this->unloaded) {
            $this->error('unloading');
            Log::warning('Unloading command');

            // Delete any heartbeat keys
            collect(Redis::keys('cattime:connections:*'))->each(function ($key) {
                Redis::del($key);
            });

            // Close the connection
            Log::channel('slack')->warning('Closing connection');

            $this->client->close();

            Log::info($this->t() . 'Closed.');

            $this->unloaded = true;
        }
    }

    public function checkShouldTerminate()
    {
        if (Redis::exists($this->terminateSignalKey) === 1) {
            Redis::del($this->terminateSignalKey);

            // Setting listener to false stops the while loop in handle()
            $this->listen = false;
        }
    }

    public function connect()
    {
        $this->client->connect(self::$host, self::$port);

        $this->authenticate();
        $this->requestCommands();
        $this->requestTags();
        $this->setNick();
        $this->joinChannels();
    }

    public function processMessage($text)
    {
        $message = new IrcMessage($text);

        $this->heartbeat($message->channel);

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

            if (! $this->underRateLimit($message)) {
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
            $this->sendMessage($cats, $message->channel);
        }
    }

    public function logToRedis(IrcMessage $message)
    {
        // Set a key in redis, with an expiration to prevent too many responses
        $messageHash = substr(sha1($message->tags['id']), 0, 8);

        Redis::setex("cattime:response:{$message->channel}:{$messageHash}", $this->rateLimitSeconds, 1);
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

    public function heartbeat($channel)
    {
        if (! $channel) {
            return;
        }

        // Log this channel's connection heartbeat so we can tell roughly
        // whether it's connected from outside of this job
        Redis::setex("cattime:connections:{$channel}", (10 * 60), 1);
    }

    public function underRateLimit($message)
    {
        // The broadcaster and bot creator are immune
        if ($message->isBroadcaster() || $message->username == 'jakebathman') {
            return true;
        }

        $keys = Redis::keys("cattime:response:{$message->channel}:*");
        if (count($keys) >= $this->rateLimitTimes) {
            // Log to Slack
            Log::channel('slack')->warning("Over rate limit in {$message->channel}, not responding", ['Key count' => count($keys)]);
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

    public function joinChannels()
    {
        foreach (config('channels') as $channel) {
            Log::info($this->t() . 'Joining channel: ' . sprintf('JOIN #%s', $channel));
            $this->client->send(sprintf('JOIN #%s', $channel));
            usleep(1000000); // 1 sec
        }
    }

    public function requestTags()
    {
        $this->client->send('CAP REQ :twitch.tv/tags');
    }

    public function requestCommands()
    {
        $this->client->send('CAP REQ :twitch.tv/commands');
    }

    public function sendMessage($message, $channel)
    {
        Log::info($this->t() . "Sending {$message} to {$channel}\n");

        $this->client->send(sprintf('PRIVMSG #%s :%s', $channel, $message));
    }

    public function t()
    {
        $t = now()->format('Y-m-d H:i:s');

        return "[{$t}] ";
    }
}
