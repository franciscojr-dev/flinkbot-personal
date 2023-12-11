<?php

require __DIR__ . '/vendor/autoload.php';

use Flinkbot\Server\Server;
use Flinkbot\Server\Handler;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;

$welcome = <<<TXT
+-------------------+
|   Flinkbot Server |
|                   |
|    Service Main   |
+-------------------+
TXT;

function now(): string {
    return (new DateTime())->format('Y-m-d H:i:s.u');
}

$server = new Server(
    '0.0.0.0:8055',
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
        Handler::AFTER_DATA => function (ConnectionInterface $connection, mixed $data) {
            if (!empty($data)) {
                $data = json_decode($data, true);
    
                if ($data) {
                    switch ($data['type'] ?? '') {
                        case 'begin_bot':
                            $conBot = new React\Socket\Connector();
                            $customerId = $data['data']['customerId'] ?? 0;
                            $serverOn = false;

                            $conBot
                                ->connect("unix:///srv/flinkbot/customer-{$customerId}.sock")
                                ->then(
                                    function () use (&$serverOn) {
                                        $serverOn = true;
                                    },
                                    function () use (&$serverOn) {
                                        $serverOn = false;
                                    }
                                );

                            if (!$serverOn) {
                                $fileProcess = __DIR__ . '/process.php';
                                $fileLog = __DIR__ . '/logs/bot.log';
                                $cmd = sprintf('/usr/bin/php %s --type=server --customer=%d > %s& echo $!', $fileProcess, $customerId, $fileLog);
                                
                                $pid = (int) trim(shell_exec($cmd));
                                echo now()." - Bot has been successfully initialized {$pid}\n";
        
                                if ($pid) {
                                    $connection->write("=> Bot has been successfully initialized");
                                }
                            }
    
                            break;
                        case 'end_bot':
                            $conBot = new React\Socket\Connector();
                            $customerId = $data['data']['customerId'] ?? 0;
                            $serverOn = false;

                            $conBot
                                ->connect("unix:///srv/flinkbot/customer-{$customerId}.sock")
                                ->then(
                                    function (ConnectionInterface $conBot) use (&$serverOn, $customerId)
                                    {
                                        $serverOn = true;

                                        $conBot->on('data', function ($data) {
                                            echo $data.PHP_EOL;
                                        });
                                        $conBot->on('close', function () {
                                            echo '=> Closed' . PHP_EOL;
                                        });
                                    
                                        $data = json_encode([
                                            'type' => 'stop_force',
                                            'data' => [],
                                        ]);
                                    
                                        $conBot->write($data);
                                    },
                                    function (Exception $e) use (&$serverOn) {
                                        $serverOn = false;
                                        //echo 'Error: ' . $e->getMessage() . PHP_EOL;
                                    }
                                );

                            if ($serverOn) {
                                echo now()." - Bot has been shut down\n";
                            
                                $connection->write("=> Bot has been shut down");
                            } else {
                                $status = $serverOn ? 'online' : 'offline';

                                echo now()." - Bot {$status}\n";
                                
                                $connection->write("=> Bot {$status}");
                            }

                            break;
                        case 'run_bot';
                            $conBot = new React\Socket\Connector();
                            $customerId = $data['data']['customerId'] ?? 0;
                            $serverOn = false;

                            $conBot
                                ->connect("unix:///srv/flinkbot/customer-{$customerId}.sock")
                                ->then(
                                    function (ConnectionInterface $conBot) use (&$serverOn)
                                    {
                                        $serverOn = true;

                                        $conBot->on('data', function ($data) {
                                            echo $data.PHP_EOL;
                                        });
                                        $conBot->on('close', function () {
                                            echo '=> Closed' . PHP_EOL;
                                        });
                                    
                                        $data = json_encode([
                                            'type' => 'bot',
                                            'data' => [],
                                        ]);
                                    
                                        $conBot->write($data);
                                    },
                                    function (Exception $e) use (&$serverOn) {
                                        $serverOn = false;
                                        //echo 'Error: ' . $e->getMessage() . PHP_EOL;
                                    }
                                );

                            if ($serverOn) {
                                echo now()." - The bot started running\n";
                            
                                $connection->write("=> The bot started running");
                            } else {
                                $status = $serverOn ? 'online' : 'offline';

                                echo now()." - Bot {$status}\n";
                                
                                $connection->write("=> Bot {$status}");
                            }

                            break;
                        case 'stop_bot':
                            $conBot = new React\Socket\Connector();
                            $customerId = $data['data']['customerId'] ?? 0;
                            $serverOn = false;

                            $conBot
                                ->connect("unix:///srv/flinkbot/customer-{$customerId}.sock")
                                ->then(
                                    function (ConnectionInterface $conBot) use (&$serverOn, $data)
                                    {
                                        $serverOn = true;

                                        $conBot->on('data', function ($data) {
                                            echo $data.PHP_EOL;
                                        });
                                        $conBot->on('close', function () {
                                            echo '=> Closed' . PHP_EOL;
                                        });
                                    
                                        $data = json_encode([
                                            'type' => 'stop',
                                            'data' => $data['data'],
                                        ]);
                                    
                                        $conBot->write($data);
                                    },
                                    function (Exception $e) use (&$serverOn) {
                                        $serverOn = false;
                                        //echo 'Error: ' . $e->getMessage() . PHP_EOL;
                                    }
                                );

                            if ($serverOn) {
                                echo now()." - Bot has been shut down\n";
                            
                                $connection->write("=> Bot has been shut down");
                            } else {
                                $status = $serverOn ? 'online' : 'offline';

                                echo now()." - Bot {$status}\n";
                                
                                $connection->write("=> Bot {$status}");
                            }

                            break;
                        case 'check_status':
                            $conBot = new React\Socket\Connector();
                            $customerId = $data['data']['customerId'] ?? 0;
                            $serverOn = false;

                            $conBot
                                ->connect("unix:///srv/flinkbot/customer-{$customerId}.sock")
                                ->then(
                                    function () use (&$serverOn) {
                                        $serverOn = true;
                                    },
                                    function () use (&$serverOn) {
                                        $serverOn = false;
                                    }
                                );

                            $status = $serverOn ? 'online' : 'offline';
                            echo now()." - Bot {$status}\n";
                            
                            $connection->write("=> Bot {$status}");
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
