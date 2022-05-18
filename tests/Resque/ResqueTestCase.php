<?php

namespace Tests\Resque;

use PHPUnit\Framework\TestCase;
use Redis;
use Freelancehunt\Resque\Resque_Redis;
use Freelancehunt\MonologInit\MonologInit;

class ResqueTestCase extends TestCase
{
    protected Redis $redis;

    protected function setUp(): void
    {
        $this->redis = new Resque_Redis('redis', 6379);
        $this->redis->prefix(REDIS_NAMESPACE);
        $this->redis->select(REDIS_DATABASE);

        $this->redis->flushAll();
    }

    protected function tearDown(): void
    {
        $this->redis->flushAll();
    }

    protected function initLogger(): MonologInit
    {
        return new MonologInit('Stream', '');
    }
}
