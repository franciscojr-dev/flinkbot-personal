<?php

declare(strict_types=1);

namespace Flinkbot\Server;

use Exception;
use InvalidArgumentException;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

class Handler
{
    public const BEFORE_START = 'beforeStart';
    public const AFTER_START = 'afterStart';

    public const BEFORE_TIMEOUT = 'beforeTimeout';
    public const AFTER_TIMEOUT = 'afterTimeout';

    public const BEFORE_CONNECTION = 'beforeConnection';
    public const AFTER_CONNECTION = 'afterConnection';

    public const BEFORE_DATA = 'beforeData';
    public const AFTER_DATA = 'afterData';

    public const BEFORE_CLOSE = 'beforeClose';
    public const AFTER_CLOSE = 'afterClose';

    public const BEFORE_ERROR = 'beforeError';
    public const AFTER_ERROR = 'afterError';

    public const VALID_EVENTS = [
        self::BEFORE_START,
        self::AFTER_START,
        self::BEFORE_TIMEOUT,
        self::AFTER_TIMEOUT,
        self::BEFORE_CONNECTION,
        self::AFTER_CONNECTION,
        self::BEFORE_DATA,
        self::AFTER_DATA,
        self::BEFORE_CLOSE,
        self::AFTER_CLOSE,
        self::BEFORE_ERROR,
        self::AFTER_ERROR,
    ];
    
    private ConnectionInterface $connection;
    private TimerInterface $timer;
    private bool $timeOver = false;
    private array $events = [];

    public function __construct(
        private readonly SocketServer $socket,
        private readonly LoopInterface $loop,
        private readonly int $timeout
    ) {
        $this->socket->on('server.up', [$this, 'onServerUp']);
        $this->socket->on('server.run', [$this, 'onServerRun']);

        $this->socket->on('server.timeout', [$this, 'onTimeout']);
        $this->socket->on('server.timeOver', [$this, 'onTimeOver']);

        $this->socket->on('connection', [$this, 'onConnection']);
        $this->socket->on('error', [$this, 'onError']);
    }

    public function setEvents(array $events): void
    {
        foreach ($events as $type => $callback) {
            if (!in_array($type, self::VALID_EVENTS)) {
                throw new InvalidArgumentException("Invalid event type: {$type}");
            }
    
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("Handler for {$type} is not callable");
            }
        }

        $this->events = $events;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    private function getEvent(string $event): ?callable
    {
        return $this->events[$event] ?? null;
    }

    private function callback(?callable $callback, array $args = []): void
    {
        if ($callback) {
            try {
                call_user_func($callback, ...$args);
            } catch (Exception $e) {
                $this->socket->emit('error', [$e->getMessage()]);
            }
        }
    }

    private function timeout(): void
    {
        $this->timer = $this->loop->addTimer($this->timeout, function () {
            $this->timeOver = true;
            $this->socket->emit('server.timeOver', [$this->connection]);
        });

        $this->socket->emit('server.timeout', [$this->timer]);
        $this->timeOver = false;
    }

    public function onServerUp(SocketServer $socket): void
    {
        $this->callback($this->getEvent(self::BEFORE_START), [$socket, $this->loop]);
    }

    public function onServerRun(SocketServer $socket): void
    {
        $this->callback($this->getEvent(self::AFTER_START), [$socket, $this->loop]);
    }

    public function onTimeout(TimerInterface $timer): void
    {
        $this->timer = $timer;
        $this->callback($this->getEvent(self::BEFORE_TIMEOUT), [$this->timer, $this->loop]);
    }

    public function onTimeOver(ConnectionInterface $connection): void
    {
        $this->callback($this->getEvent(self::AFTER_TIMEOUT), [$connection, $this->loop]);
    }

    public function onConnection(ConnectionInterface $connection): void
    {
        $this->callback($this->getEvent(self::BEFORE_CONNECTION), [$this->loop]);
        $this->connection = $connection;
        $this->callback($this->getEvent(self::AFTER_CONNECTION), [$this->connection, $this->loop]);

        $this->timeout();

        $this->connection->on('data', [$this, 'onData']);
        $this->connection->on('close', [$this, 'onClose']);
    }

    public function onData(mixed $data): void
    {
        $this->loop->cancelTimer($this->timer);

        $this->callback($this->getEvent(self::BEFORE_DATA), [$this->connection, $this->loop]);
        $this->callback($this->getEvent(self::AFTER_DATA), [$this->connection, $data, $this->loop]);

        $this->timeout();
    }

    public function onClose(): void
    {
        $this->callback($this->getEvent(self::BEFORE_CLOSE), [$this->loop]);

        if (!$this->timeOver) {
            $this->loop->cancelTimer($this->timer);
            $this->callback($this->getEvent(self::AFTER_CLOSE), [$this->loop]);
        }
    }

    public function onError(string $error): void
    {
        $this->loop->cancelTimer($this->timer);

        $this->callback($this->getEvent(self::BEFORE_ERROR), [$this->connection, $error]);
        $this->callback($this->getEvent(self::AFTER_ERROR), [$this->connection, $error]);
    }
}
