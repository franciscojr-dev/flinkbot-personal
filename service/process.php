<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Flinkbot\Bot\Bot;
use Flinkbot\Bot\Operation;

use Flinkbot\Server\Server;
use Flinkbot\Server\Handler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

$opts = getopt(
    'c::t::b::s::',
    ['customer::', 'type::', 'bot::', 'symbol::']
);
$type = (string) ($opts['type'] ?? '');
$customerId = (int) ($opts['customer'] ?? 0);
$botId = (int) ($opts['bot'] ?? 0);
$symbol = (string) ($opts['symbol'] ?? '');

$welcome = <<<TXT
+-------------------+
|   Flinkbot Server |
|                   |
|    Service Aux.   |
+-------------------+
TXT;

function now(): string {
    return (new DateTime())->format('Y-m-d H:i:s.u');
}

$init = now();

if ($type == 'symbol') {
    $operation = new Operation($botId, $symbol);
    $operation->run();
}

if ($type == 'server') {
    if (file_exists("/srv/flinkbot/customer-{$customerId}.sock")) {
        unlink("/srv/flinkbot/customer-{$customerId}.sock");
    }

    $botOperator = new Bot($customerId);

    $server = new Server(
        "unix:///srv/flinkbot/customer-{$customerId}.sock",
        [
            Handler::BEFORE_START => function () {
                echo now()." - Server is now running".PHP_EOL;
            },
            Handler::AFTER_TIMEOUT => function (ConnectionInterface $connection, LoopInterface $loop) {
                echo now()." - Connection closed due to inactivity".PHP_EOL;
    
                $connection->write("=> Connection closed due to inactivity");
    
                $loop->addTimer(0.1, function () use ($connection) {
                    $connection->close();
                });
            },
            Handler::AFTER_CONNECTION => function (ConnectionInterface $connection) use ($welcome) {
                echo now()." - New connection established".PHP_EOL;
    
                $connection->write($welcome);
            },
            Handler::AFTER_DATA => function (ConnectionInterface $connection, mixed $data) use ($botOperator) {
                if (!empty($data)) {
                    $data = json_decode($data, true);
    
                    if ($data) {
                        switch ($data['type'] ?? '') {
                            case 'bot':
                                if (!$botOperator->isRunning()) {
                                    $botOperator->run();
                                    
                                    echo "Total execution: " . gmdate('H:i:s', (int) $this->processor->timeExecution()) . "\n";
                                }
                                break;
                            case 'symbol':
                                $botId = (int) ($data['data']['botId'] ?? 0);
                                $symbol = (string) ($data['data']['symbol'] ?? '');
                                
                                if ($botOperator->isRunning()) {
                                    $operation = new Operation($botId, $symbol);
                                    $operation->run();
                                }
                                break;
                            case 'stop':
                                $botId = (int) ($data['data']['botId'] ?? 0);
                                $symbol = (string) ($data['data']['symbol'] ?? '');
                                $force = (bool) ($data['data']['force'] ?? false);
                                
                                if ($botOperator->isRunning()) {
                                    if ($symbol) {
                                        $botOperator->close($botId, $symbol, $force);
                                    } else {
                                        $botOperator->closeAll($force);
                                    }
                                }
                                break;
                            case 'stop_force':
                                exit(0);
                                break;
                        }
                    }
                }
            },
            Handler::AFTER_CLOSE => function () {
                echo now()." - Connection closed by client".PHP_EOL;
            },
            Handler::AFTER_ERROR => function (ConnectionInterface $connection, string $error) {
                echo now()." - {$error}".PHP_EOL;
    
                $connection->write($error);
            },
        ],
        5
    );
    
    $server->run();
}
