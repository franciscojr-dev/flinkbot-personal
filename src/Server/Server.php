<?php

declare(strict_types=1);

namespace Flinkbot\Server;

use LogicException;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;

class Server
{
    private LoopInterface $loop;
    private SocketServer $socket;
    private Handler $handler;
    
    public function __construct(
        private readonly string  $address,
        private readonly array $events = [],
        int $timeout = 30
    ) {
        $this->loop = Loop::get();
        $this->socket = new SocketServer($this->address);
        $this->handler = new Handler($this->socket, $this->loop, $timeout);
        $this->handler->setEvents($events);

        $this->socket->emit('server.up', [$this->socket]);
    }

    public function run(): void
    {
        if (!$this->handler) {
            throw new LogicException('Need to define the handler class');
        }

        $this->socket->emit('server.run', [$this->socket]);

        $this->loop->run();
    }
}
