<?php

namespace Freelancehunt\Fresque;

use Redis;

/**
 * Saving the workers statuses
 */
class ResqueStatus
{
    public const WORKER_KEY           = 'ResqueWorker';
    public const SCHEDULER_WORKER_KEY = 'ResqueSchedulerWorker';
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
        return $this->redis->hSet(self::WORKER_KEY, $pid, serialize($args)) !== false;
    }

    /**
     * Register a Scheduler Worker
     */
    public function registerSchedulerWorker(int $pid): bool
    {
        return (bool) $this->redis->set(self::SCHEDULER_WORKER_KEY, $pid);
    }

    /**
     * Test if a given worker is a scheduler worker
     *
     * @param Worker|string $worker Worker to test
     *
     * @return  boolean                 True if the worker is a scheduler worker
     * @since   0.0.1
     */
    public function isSchedulerWorker(mixed $worker): bool
    {
        [$host, $pid, $queue] = explode(':', (string) $worker);

        return $pid === $this->redis->get(self::SCHEDULER_WORKER_KEY);
    }

    /**
     * Check if the Scheduler Worker is already running
     *
     * @return boolean True if the scheduler worker is already running
     */
    public function isRunningSchedulerWorker(): bool
    {
        $pids         = $this->redis->hKeys(self::WORKER_KEY);
        $schedulerPid = $this->redis->get(self::SCHEDULER_WORKER_KEY);

        if ($schedulerPid !== false && is_array($pids)) {
            if (in_array($schedulerPid, $pids)) {
                return true;
            }
            // Pid is outdated, remove it
            $this->unregisterSchedulerWorker();

            return false;
        }

        return false;
    }

    /**
     * Unregister a Scheduler Worker
     *
     * @return boolean True if the scheduler worker existed and was successfully unregistered
     */
    public function unregisterSchedulerWorker(): bool
    {
        return $this->redis->del(self::SCHEDULER_WORKER_KEY) > 0;
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
