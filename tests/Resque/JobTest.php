<?php

namespace Tests\Resque;

use Freelancehunt\Resque\Resque_Job;
use Freelancehunt\Resque\Resque;
use Freelancehunt\Resque\Resque_Stat;
use Freelancehunt\Resque\Worker;
use stdClass;
use InvalidArgumentException;
use Freelancehunt\Resque\Resque_Exception;
use Tests\Test_Job;
use Tests\Test_Job_Without_Perform_Method;
use Tests\Test_Job_With_SetUp;
use Tests\Failing_Job;
use Tests\Test_Job_With_TearDown;

/**
 * Resque_Job tests.
 *
 * @package        Resque/Tests
 * @author         Chris Boulton <chris@bigcommerce.com>
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class JobTest extends ResqueTestCase
{
    protected Worker $worker;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new Worker('jobs');
        $this->worker->registerWorker();

        $logger = $this->initLogger();
        $this->worker->registerLogger($logger);
    }

    public function testJobCanBeQueued()
    {
        $this->assertTrue((bool) Resque::enqueue('jobs', Test_Job::class));
    }

    public function testQeueuedJobCanBeReserved()
    {
        Resque::enqueue('jobs', Test_Job::class);

        $job = Resque_Job::reserve('jobs');
        if ($job == false) {
            $this->fail('Job could not be reserved.');
        }
        $this->assertEquals('jobs', $job->queue);
        $this->assertEquals(Test_Job::class, $job->payload['class']);
    }

    public function testObjectArgumentsCannotBePassedToJob()
    {
        $this->expectException(InvalidArgumentException::class);
        $args       = new stdClass;
        $args->test = 'somevalue';
        Resque::enqueue('jobs', Test_Job::class, $args);
    }

    public function testQueuedJobReturnsExactSamePassedInArguments()
    {
        $args = [
            'int'        => 123,
            'numArray'   => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];
        Resque::enqueue('jobs', Test_Job::class, $args);
        $job = Resque_Job::reserve('jobs');

        $this->assertEquals($args, $job->getArguments());
    }

    public function testAfterJobIsReservedItIsRemoved()
    {
        Resque::enqueue('jobs', Test_Job::class);
        Resque_Job::reserve('jobs');
        $this->assertFalse(Resque_Job::reserve('jobs'));
    }

    public function testRecreatedJobMatchesExistingJob()
    {
        $args = [
            'int'        => 123,
            'numArray'   => [
                1,
                2,
            ],
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];

        Resque::enqueue('jobs', Test_Job::class, $args);
        $job = Resque_Job::reserve('jobs');

        // Now recreate it
        $job->recreate();

        $newJob = Resque_Job::reserve('jobs');
        $this->assertEquals($job->payload['class'], $newJob->payload['class']);
        $this->assertEquals($job->payload['args'], $newJob->getArguments());
    }

    public function testFailedJobExceptionsAreCaught()
    {
        $payload     = [
            'class' => Failing_Job::class,
            'id'    => 'randomId',
            'args'  => null,
        ];
        $job         = new Resque_Job('jobs', $payload);
        $job->worker = $this->worker;

        $this->worker->perform($job);

        $this->assertEquals(1, Resque_Stat::get('failed'));
        $this->assertEquals(1, Resque_Stat::get('failed:' . $this->worker));
    }

    public function testJobWithoutPerformMethodThrowsException()
    {
        $this->expectException(Resque_Exception::class);
        Resque::enqueue('jobs', Test_Job_Without_Perform_Method::class);
        $job         = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testInvalidJobThrowsException()
    {
        $this->expectException(Resque_Exception::class);
        Resque::enqueue('jobs', 'Invalid_Job');
        $job         = $this->worker->reserve();
        $job->worker = $this->worker;
        $job->perform();
    }

    public function testJobWithSetUpCallbackFiresSetUp()
    {
        $payload = [
            'class' => Test_Job_With_SetUp::class,
            'args'  => [
                'somevar',
                'somevar2',
            ],
        ];
        $job     = new Resque_Job('jobs', $payload);
        $job->perform();

        $this->assertTrue(Test_Job_With_SetUp::$called);
    }

    public function testJobWithTearDownCallbackFiresTearDown()
    {
        $payload = [
            'class' => Test_Job_With_TearDown::class,
            'args'  => [
                'somevar',
                'somevar2',
            ],
        ];
        $job     = new Resque_Job('jobs', $payload);
        $job->perform();

        $this->assertTrue(Test_Job_With_TearDown::$called);
    }

    public function testJobWithNamespace()
    {
        Resque::setBackend(REDIS_HOST, REDIS_DATABASE, 'php');
        $queue   = 'jobs';
        $payload = ['another_value'];
        Resque::enqueue($queue, Test_Job_With_TearDown::class, $payload);

        $this->assertEquals(Resque::queues(), ['jobs']);
        $this->assertEquals(Resque::size($queue), 1);

        Resque::setBackend(REDIS_HOST, REDIS_DATABASE, REDIS_NAMESPACE);
        $this->assertEquals(Resque::size($queue), 0);
    }
}
