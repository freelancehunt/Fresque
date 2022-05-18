<?php

namespace Freelancehunt\Fresque;

use Freelancehunt\Resque\Worker;
use Redis;

class ResqueStats
{
    public function __construct(private readonly Redis $redis)
    {
    }

    /**
     * Return a list of queues
     */
    public function getQueues(): array
    {
        return $this->redis->smembers('queues');
    }

    /**
     * Return the number of jobs in a queue
     *
     * @param string $queue name of the queue
     *
     * @return int number of queued jobs
     */
    public function getQueueLength(string $queue): int
    {
        return $this->redis->llen('queue:' . $queue);
    }

    /**
     * Return a list of workers
     *
     * @return array of workers
     */
    public function getWorkers(): array
    {
        return (array) Worker::all();
    }

    /**
     * Return the start date of a worker
     *
     * @param string $worker Name of the worker
     *
     * @return string ISO-8601 formatted date
     */
    public function getWorkerStartDate(string $worker): string
    {
        return $this->redis->get('worker:' . $worker . ':started');
    }
}
