<?php

namespace Tests\Resque;

use Freelancehunt\Resque\Event;
use Freelancehunt\Resque\Job;
use Freelancehunt\Resque\Job\DontPerform;
use Freelancehunt\Resque\Resque;
use Freelancehunt\Resque\Worker;
use Tests\Test_Job;

class EventTest extends ResqueTestCase
{
	private $callbacksHit = array();

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
        Event::clearListeners();
        $this->callbacksHit = [];
    }

	public function getEventTestJob()
	{
		$payload = array(
			'class' => Test_Job::class,
			'id' => 'randomId',
			'args' => array(
				'somevar',
			),
		);
		$job = new Job('jobs', $payload);
		$job->worker = $this->worker;
		return $job;
	}

	public function eventCallbackProvider()
	{
		return array(
			array('beforePerform', 'beforePerformEventCallback'),
			array('afterPerform', 'afterPerformEventCallback'),
			array('afterFork', 'afterForkEventCallback'),
		);
	}

	/**
	 * @dataProvider eventCallbackProvider
	 */
	public function testEventCallbacksFire($event, $callback)
	{
		Event::listen($event, array($this, $callback));

		$job = $this->getEventTestJob();
		$this->worker->perform($job);
		$this->worker->work(0);

		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testBeforeForkEventCallbackFires()
	{
		$event = 'beforeFork';
		$callback = 'beforeForkEventCallback';

		Event::listen($event, array($this, $callback));
		Resque::enqueue('jobs', Test_Job::class, array(
			'somevar'
		));
		$job = $this->getEventTestJob();
		$this->worker->work(0);
		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testBeforePerformEventCanStopWork()
	{
		$callback = 'beforePerformEventDontPerformCallback';
		Event::listen('beforePerform', array($this, $callback));

		$job = $this->getEventTestJob();

		$this->assertFalse($job->perform());
		$this->assertContains($callback, $this->callbacksHit, $callback . ' callback was not called');
		$this->assertFalse(Test_Job::$called, 'Job was still performed though DontPerform was thrown');
	}

	public function testAfterEnqueueEventCallbackFires()
	{
		$callback = 'afterEnqueueEventCallback';
		$event = 'afterEnqueue';

		Event::listen($event, array($this, $callback));
		Resque::enqueue('jobs', Test_Job::class, array(
			'somevar'
		));
		$this->assertContains($callback, $this->callbacksHit, $event . ' callback (' . $callback .') was not called');
	}

	public function testStopListeningRemovesListener()
	{
		$callback = 'beforePerformEventCallback';
		$event = 'beforePerform';

		Event::listen($event, array($this, $callback));
		Event::stopListening($event, array($this, $callback));

		$job = $this->getEventTestJob();
		$this->worker->perform($job);
		$this->worker->work(0);

		$this->assertNotContains($callback, $this->callbacksHit,
			$event . ' callback (' . $callback .') was called though Event::stopListening was called'
		);
	}


	public function beforePerformEventDontPerformCallback($instance)
	{
		$this->callbacksHit[] = __FUNCTION__;
		throw new DontPerform;
	}

	public function assertValidEventCallback($function, $job)
	{
		$this->callbacksHit[] = $function;
		if (!$job instanceof Job) {
			$this->fail('Callback job argument is not an instance of Job');
		}
		$args = $job->getArguments();
		$this->assertEquals($args[0], 'somevar');
	}

	public function afterEnqueueEventCallback($class, $args, $queue)
	{
		$this->callbacksHit[] = __FUNCTION__;
        $this->assertEquals('jobs', $queue);
		$this->assertEquals(Test_Job::class, $class);
		$this->assertEquals(array(
			'somevar',
		), $args);
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
