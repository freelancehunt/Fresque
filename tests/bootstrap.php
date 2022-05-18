<?php

namespace Tests;

use Freelancehunt\Resque\Resque;
use Exception;

include "./vendor/autoload.php";

define('REDIS_HOST', 'redis:6379');
define('REDIS_DATABASE', 7);
define('REDIS_NAMESPACE', 'resque');

Resque::setBackend(REDIS_HOST, REDIS_DATABASE, REDIS_NAMESPACE);

class Test_Job
{
    public static bool $called = false;

    public function perform(): void
    {
        self::$called = true;
    }
}

class Failing_Job_Exception extends Exception
{

}

class Failing_Job
{
    public function perform()
    {
        throw new Failing_Job_Exception('Message!');
    }
}

class Test_Job_Without_Perform_Method
{

}

class Test_Job_With_SetUp
{
    public static $called = false;
    public        $args   = false;

    public function setUp()
    {
        self::$called = true;
    }

    public function perform()
    {

    }
}

class Test_Job_With_TearDown
{
    public static $called = false;
    public        $args   = false;

    public function perform()
    {

    }

    public function tearDown()
    {
        self::$called = true;
    }
}
