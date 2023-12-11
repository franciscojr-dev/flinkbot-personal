<?php

declare(strict_types=1);

namespace Flinkbot\Core;

class SingleExecution
{
    /**
     * @var mixed
     */
    private mixed $lockFile;

    /**
     * Constructor
     *
     * @param string $scriptName
     */
    public function __construct(string $scriptName)
    {
        $this->lockFile = fopen(__DIR__ . '/' . $scriptName . '.lock', 'c');
    }

    /**
     * Destruct
     */
    public function __destruct()
    {
        fclose($this->lockFile);
    }

    /**
     * Acquire lock
     *
     * @return bool
     */
    public function acquireLock(): bool
    {
        return flock($this->lockFile, LOCK_EX | LOCK_NB);
    }

    /**
     * Release lock
     *
     * @return void
     */
    public function releaseLock(): void
    {
        flock($this->lockFile, LOCK_UN);
    }
}
