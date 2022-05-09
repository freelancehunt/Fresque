<?php

namespace Tests\Resque;

use Resque\Resque;
use Resque\Worker;
use Resque\Job\Resque_Job_Status;
use Resque\Resque_Job;
use MonologInit\MonologInit;

class JobStatusTest extends ResqueTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register a worker to test with
        $this->worker = new Worker('jobs');
        $this->worker->registerWorker();

        $logger = $this->initLogger();
        $this->worker->registerLogger($logger);
    }

    public function testJobStatusCanBeTracked()
    {
        $token  = Resque::enqueue('jobs', Test_Job::class, null, true);
        $status = new Resque_Job_Status($token);
        $this->assertTrue($status->isTracking());
    }

    public function testJobStatusIsReturnedViaJobInstance()
    {
        $token = Resque::enqueue('jobs', Test_Job::class, null, true);
        $job   = Resque_Job::reserve('jobs');
        $this->assertEquals(Resque_Job_Status::STATUS_WAITING, $job->getStatus());
    }

    public function testQueuedJobReturnsQueuedStatus()
    {
        $token  = Resque::enqueue('jobs', Test_Job::class, null, true);
        $status = new Resque_Job_Status($token);
        $this->assertEquals(Resque_Job_Status::STATUS_WAITING, $status->get());
    }

    public function testRunningJobReturnsRunningStatus()
    {
        $token = Resque::enqueue('jobs', Failing_Job::class, null, true);
        $job   = $this->worker->reserve();
        $this->worker->workingOn($job);
        $status = new Resque_Job_Status($token);
        $this->assertEquals(Resque_Job_Status::STATUS_RUNNING, $status->get());
    }

    public function testFailedJobReturnsFailedStatus()
    {
        $token = Resque::enqueue('jobs', Failing_Job::class, null, true);
        $this->worker->work(0);
        $status = new Resque_Job_Status($token);
        $this->assertEquals(Resque_Job_Status::STATUS_FAILED, $status->get());
    }

    public function testCompletedJobReturnsCompletedStatus()
    {
        $token = Resque::enqueue('jobs',  Test_Job::class, null, true);
        $this->worker->work(0);
        $status = new Resque_Job_Status($token);
        $this->assertEquals(Resque_Job_Status::STATUS_COMPLETE, $status->get());
    }

    public function testStatusIsNotTrackedWhenToldNotTo()
    {
        $token  = Resque::enqueue('jobs', Test_Job::class, null, false);
        $status = new Resque_Job_Status($token);
        $this->assertFalse($status->isTracking());
    }

    public function testStatusTrackingCanBeStopped()
    {
        Resque_Job_Status::create('test');
        $status = new Resque_Job_Status('test');
        $this->assertEquals(Resque_Job_Status::STATUS_WAITING, $status->get());
        $status->stop();
        $this->assertFalse($status->get());
    }

    public function testRecreatedJobWithTrackingStillTracksStatus()
    {
        $originalToken = Resque::enqueue('jobs', Test_Job::class, null, true);
        $job           = $this->worker->reserve();

        // Mark this job as being worked on to ensure that the new status is still
        // waiting.
        $this->worker->workingOn($job);

        // Now recreate it
        $newToken = $job->recreate();

        // Make sure we've got a new job returned
        $this->assertNotEquals($originalToken, $newToken);

        // Now check the status of the new job
        $newJob = Resque_Job::reserve('jobs');
        $this->assertEquals(Resque_Job_Status::STATUS_WAITING, $newJob->getStatus());
    }
}
