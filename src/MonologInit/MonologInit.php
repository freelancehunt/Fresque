<?php
/**
 * Monolog Init File
 *
 * Very basic and light Dependency Injector Container for Monolog
 *
 * PHP version 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author        Wan Qi Chen <kami@kamisama.me>
 * @copyright     Copyright 2012, Wan Qi Chen <kami@kamisama.me>
 * @link          https://github.com/kamisama/Monolog-Init
 * @package       MonologInit
 * @since         0.1.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

namespace Freelancehunt\MonologInit;

use Monolog\Handler\Handler;
use Monolog\Logger;

class MonologInit
{
    public string|null    $handler  = null;
    public string|null    $target   = null;
    protected Logger|null $instance = null;

    const VERSION = '0.2.1';

    public function __construct($handler = false, $target = false)
    {
        if ($handler === false || $target === false) {
            return null;
        }

        $this->createLoggerInstance($handler, $target);
    }

    /**
     * Return a Monolog Logger instance
     *
     * @return Logger instance, ready to use
     */
    public function getInstance(): Logger
    {
        return $this->instance;
    }

    /**
     * Create a Monolog\Logger instance and attach a handler
     *
     * @param string $handler Name of the handler, without the "Handler" part
     * @param string $target  Comma separated list of arguments to pass to the handler
     *
     * @return void
     */
    protected function createLoggerInstance(string $handler, string $target): void
    {
        $handlerClassName = $handler . 'Handler';

        // TODO: Remove checker and replace with try/catch?
        if (class_exists('\Monolog\Handler\\' . $handlerClassName)) {
            if (null !== $handlerInstance = $this->createHandlerInstance($handlerClassName, $target)) {
                $this->instance = new Logger('main');
                $this->instance->pushHandler($handlerInstance);
            }

            $this->handler = $handler;
            $this->target  = $target;
        }
    }

    /**
     * Create Monolog Handler instance
     *
     * @param string $className   Monolog handler classname
     * @param string $handlerArgs Comma separated list of arguments to pass to the handler
     *
     * @return Handler|null instance if successfully, null otherwise
     */
    protected function createHandlerInstance(string $className, string $handlerArgs): ?Handler
    {
        if (method_exists($this, 'init' . $className)) {
            return call_user_func([$this, 'init' . $className], explode(',', $handlerArgs));
        } else {
            return null;
        }
    }

    public function initRedisHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\RedisHandler');
        $redis   = new \Redis();
        $redis->connect(array_shift($args));
        array_unshift($args, $redis);

        return $reflect->newInstanceArgs($args);
    }

    public function initCubeHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\CubeHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initRotatingFileHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\RotatingFileHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initStreamHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\StreamHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initChromePHPHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\ChromePHPHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initSyslogHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\SyslogHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initSocketHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\SocketHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initMongoDBHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\MongoDBHandler');
        $mongo   = new \Mongo(array_shift($args));
        array_unshift($args, $mongo);

        return $reflect->newInstanceArgs($args);
    }

    public function initCouchDBHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\CouchDBHandler');
        if (isset($args[0])) {
            $args[0] = explode(':', $args[0]);
        }

        return $reflect->newInstanceArgs($args);
    }

    public function initHipChatHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\HipChatHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initPushOverHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\PushOverHandler');

        return $reflect->newInstanceArgs($args);
    }

    public function initZendMonitorHandler(array $args): ?object
    {
        $reflect = new \ReflectionClass('\Monolog\Handler\ZendMonitorHandler');

        return $reflect->newInstanceArgs($args);
    }
}
