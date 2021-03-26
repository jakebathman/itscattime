<?php

namespace App\Twitch\Socket;

use App\Twitch\Socket\SocketContract;
use Illuminate\Support\Facades\Log;

class IrcSocket implements SocketContract
{
    public $socket;

    public function connect($host, $port)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (socket_connect($this->socket, $host, $port) === false) {
            return null;
        }
    }

    public function close()
    {
        socket_close($this->socket);
    }

    public function isConnected()
    {
        return ! is_null($this->socket);
    }

    public function getLastError()
    {
        return socket_last_error($this->socket);
    }

    public function read($size = null)
    {
        if (! $size) {
            $size = 1024;
        }

        if (! $this->isConnected()) {
            return null;
        }

        try {
            return socket_read($this->socket, $size);
        } catch (\Throwable $th) {
            Log::error('Error reading socket: ' . $this->getLastError());

            return null;
        }
    }

    public function send($message)
    {
        if (! $this->isConnected()) {
            return null;
        }

        return socket_write($this->socket, $message . "\n");
    }
}
