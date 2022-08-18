<?php

namespace Tests\Fresque;

use PHPUnit\Framework\TestCase;
use Freelancehunt\Fresque\ResqueStatus;
use Redis;

class ResqueStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->redis = new Redis();
        $this->redis->connect('redis', '6379');
        $this->redis->select(6);

        $this->ResqueStatus = new ResqueStatus($this->redis);

        $this->workers = array();
        $this->workers[100] = new Worker('One:100:queue5', 5);
        $this->workers[101] = new Worker('Two:101:queue1', 10);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis->del(ResqueStatus::WORKER_KEY);
        $this->redis->del(ResqueStatus::PAUSED_WORKER_KEY);
    }

    public function testAddWorker()
    {
        $workers = array(
            "0125" => array('name' => 'WorkerZero'),
            "6523" => array('name' => 'workerOne', 'debug' => true)
        );
        $this->redis->hSet(ResqueStatus::WORKER_KEY, "0125", serialize($workers["0125"]));

        $res = $this->ResqueStatus->addWorker(6523, $workers["6523"]);

        $this->assertTrue($res);

        $this->assertEquals(2, $this->redis->hLen(ResqueStatus::WORKER_KEY));
        $datas = $this->redis->hGetAll(ResqueStatus::WORKER_KEY);

        $this->assertEquals($workers["0125"], unserialize($datas["0125"]));
        unset($workers[1]['debug']);
        $this->assertEquals($workers["6523"], unserialize($datas["6523"]));
    }

    public function testGetWorkers()
    {
        foreach ($this->workers as $pid => $worker) {
            $this->redis->hSet(ResqueStatus::WORKER_KEY, $pid, serialize($worker));
        }

        $this->assertEquals($this->workers, $this->ResqueStatus->getWorkers());
    }

    public function testSetPausedWorker()
    {
        $worker = 'workerName';
        $this->ResqueStatus->setPausedWorker($worker);

        $this->assertEquals(1, $this->redis->sCard(ResqueStatus::PAUSED_WORKER_KEY));
        $this->assertContains($worker, $this->redis->sMembers(ResqueStatus::PAUSED_WORKER_KEY));
    }

    public function testSetActiveWorker()
    {
        $workers = array('workerOne', 'workerTwo');

        $this->redis->sAdd(ResqueStatus::PAUSED_WORKER_KEY, $workers[0]);
        $this->redis->sAdd(ResqueStatus::PAUSED_WORKER_KEY, $workers[1]);

        $this->ResqueStatus->setPausedWorker($workers[0], false);

        $pausedWorkers = $this->redis->sMembers(ResqueStatus::PAUSED_WORKER_KEY);
        $this->assertCount(1, $pausedWorkers);
        $this->assertEquals(array($workers[1]), $pausedWorkers);
    }

    public function testGetPausedWorker()
    {
        $workers = array('workerOne', 'workerTwo');

        $this->redis->sAdd(ResqueStatus::PAUSED_WORKER_KEY, $workers[0]);
        $this->redis->sAdd(ResqueStatus::PAUSED_WORKER_KEY, $workers[1]);

        $pausedWorkers = $this->ResqueStatus->getPausedWorkers();

        sort($pausedWorkers);
        sort($workers);

        $this->assertEquals($workers, $pausedWorkers);
    }

    /**
     * Test that getPausedWorkers always return an array
     */
    public function testGetPausedWorkerWhenThereIsNoPausedWorkers()
    {
        $this->assertEquals(array(), $this->ResqueStatus->getPausedWorkers());
    }

    public function testRemoveWorker()
    {
        foreach ($this->workers as $pid => $worker) {
            $this->redis->hSet(ResqueStatus::WORKER_KEY, $pid, serialize($worker));
        }

        $this->ResqueStatus->removeWorker(100);

        $w = array_keys($this->workers);
        unset($w[100]);

        $ww = $this->redis->hKeys(ResqueStatus::WORKER_KEY);

        $this->assertEquals(sort($w), sort($ww));
    }

    public function testClearWorkers()
    {
        $this->redis->set(ResqueStatus::WORKER_KEY, 'one');
        $this->redis->set(ResqueStatus::PAUSED_WORKER_KEY, 'two');

        $this->ResqueStatus->clearWorkers();

        $this->assertSame(0, $this->redis->exists(ResqueStatus::WORKER_KEY));
        $this->assertSame(0, $this->redis->exists(ResqueStatus::PAUSED_WORKER_KEY));
    }
}

class Worker
{
    public $name;

    public $interval;

    public function __construct($name, $interval)
    {
        $this->name = $name;
        $this->interval = $interval;
    }

    public function __toString()
    {
        return $this->name;
    }
}
