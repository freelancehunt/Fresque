<?php

namespace Freelancehunt\Fresque;

use Redis;

/**
 * Saving the workers statuses
 */
class ResqueStatus
{
    public const WORKER_KEY           = 'ResqueWorker';
    public const PAUSED_WORKER_KEY    = 'PausedWorker';

    public function __construct(protected Redis $redis)
    {
    }

    /**
     * Save the workers arguments
     *
     * Used when restarting the worker
     *
     * @param array $args Worker settings
     */
    public function addWorker(int $pid, array $args): bool
    {
        return $this->redis->hSet(self::WORKER_KEY, $pid, serialize($args)) !== false;;
    }

    /**
     * Return all started workers arguments
     *
     * @return array An array of settings, by worker
     */
    public function getWorkers(): array
    {
        $workers = $this->redis->hGetAll(self::WORKER_KEY);
        $temp    = [];

        foreach ($workers as $name => $value) {
            $temp[$name] = unserialize($value);
        }

        return $temp;
    }

    public function removeWorker(int $pid): void
    {
        $this->redis->hDel(self::WORKER_KEY, $pid);
    }

    /**
     * Clear all workers saved arguments
     */
    public function clearWorkers(): void
    {
        $this->redis->del(self::WORKER_KEY);
        $this->redis->del(self::PAUSED_WORKER_KEY);
    }

    /**
     * Mark a worker as paused/active
     *
     * @param string $workerName Name of the paused worker
     * @param bool   $paused     Whether to mark the worker as paused or active
     */
    public function setPausedWorker(string $workerName, bool $paused = true): void
    {
        if ($paused) {
            $this->redis->sadd(self::PAUSED_WORKER_KEY, $workerName);
        } else {
            $this->redis->srem(self::PAUSED_WORKER_KEY, $workerName);
        }
    }

    /**
     * Return a list of paused workers
     *
     * @return  array   An array of paused workers' name
     */
    public function getPausedWorkers(): array
    {
        return (array) $this->redis->smembers(self::PAUSED_WORKER_KEY);
    }
}
