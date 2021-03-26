<?php

namespace App\Twitch\Socket;

interface SocketContract
{
    public function connect($host, $port);
    public function close();
    public function isConnected();
    public function getLastError();
    public function read($size = null);
    public function send($message);
}
