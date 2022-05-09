<?php

namespace Tests\Resque;

use Resque\Worker;
use Resque\Resque_Event;
use Resque\Resque;
use Resque\Resque_Job;
use Resque\Job\Resque_Job_DontPerform;

class EventTest extends ResqueTestCase
{
    private $callbacksHit = [];

    protected function setUp(): void
    {
        parent::setUp();

        Test_Job::$called = false;

        // Register a worker to test with
        $this->worker = new Worker('jobs');
        $this->worker->registerWorker();

        $logger = $this->initLogger();
        $this->worker->registerLogger($logger);
    }

    protected function tearDown(): void
    {
        Resque_Event::clearListeners();
        $this->callbacksHit = [];
    }

    public function getEventTestJob()
    {
        $payload     = [
            'class' => Test_Job::class,
            'id'    => 'randomId',
            'args'  => [
                'somevar',
            ],
        ];
        $job         = new Resque_Job('jobs', $payload);
        $job->worker = $this->worker;

        return $job;
    }

    public function eventCallbackProvider()
    {
        return [
            ['beforePerform', 'beforePerformEventCallback'],
            ['afterPerform', 'afterPerformEventCallback'],
            ['afterFork', 'afterForkEventCallback'],
        ];
    }

    /**
     * @dataProvider eventCallbackProvider
     */
    public function testEventCallbacksFire($event, $callback)
    {
        Resque_Event::listen($event, [$this, $callback]);

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testBeforeForkEventCallbackFires()
    {
        $event    = 'beforeFork';
        $callback = 'beforeForkEventCallback';

        Resque_Event::listen($event, [$this, $callback]);
        Resque::enqueue('jobs', Test_Job::class, [
            'somevar',
        ]);
        $job = $this->getEventTestJob();
        $this->worker->work(0);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testBeforePerformEventCanStopWork()
    {
        $callback = 'beforePerformEventDontPerformCallback';
        Resque_Event::listen('beforePerform', [$this, $callback]);

        $job = $this->getEventTestJob();

        $this->assertFalse($job->perform());
        $this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
        $this->assertFalse(Test_Job::$called, 'Job was still performed though Resque_Job_DontPerform was thrown');
    }

    public function testAfterEnqueueEventCallbackFires()
    {
        $callback = 'afterEnqueueEventCallback';
        $event    = 'afterEnqueue';

        Resque_Event::listen($event, [$this, $callback]);
        Resque::enqueue('jobs', Test_Job::class, [
            'somevar',
        ]);
        $this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback . ') was not called');
    }

    public function testStopListeningRemovesListener()
    {
        $callback = 'beforePerformEventCallback';
        $event    = 'beforePerform';

        Resque_Event::listen($event, [$this, $callback]);
        Resque_Event::stopListening($event, [$this, $callback]);

        $job = $this->getEventTestJob();
        $this->worker->perform($job);
        $this->worker->work(0);

        $this->assertNotContains($callback, $this->callbacksHit,
            $event . ' callback (' . $callback . ') was called though Resque_Event::stopListening was called'
        );
    }

    public function beforePerformEventDontPerformCallback($instance)
    {
        $this->callbacksHit[] = __FUNCTION__;
        throw new Resque_Job_DontPerform();
    }

    public function assertValidEventCallback($function, $job)
    {
        $this->callbacksHit[] = $function;
        if (!$job instanceof Resque_Job) {
            $this->fail('Callback job argument is not an instance of Resque_Job');
        }
        $args = $job->getArguments();
        // if (is_array($args[0])) {
        //     var_export($args);exit();
        // }
        $this->assertEquals('somevar', $args[0]);
    }

    public function afterEnqueueEventCallback($class, $args, $queue)
    {
        $this->callbacksHit[] = __FUNCTION__;
        $this->assertEquals('jobs', $queue);
        $this->assertEquals(Test_Job::class, $class);
        $this->assertEquals([
            'somevar',
        ], $args);
    }

    public function beforePerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterPerformEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function beforeForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }

    public function afterForkEventCallback($job)
    {
        $this->assertValidEventCallback(__FUNCTION__, $job);
    }
}
