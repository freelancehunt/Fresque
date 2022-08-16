<?php

namespace Freelancehunt\Resque;

use Redis;

class ResqueRedis extends Redis
{
    private static $defaultNamespace = 'resque:';

    public function __construct($host, $port, $timeout = 5, $password = null)
    {
        parent::__construct();

        $this->host     = $host;
        $this->port     = $port;
        $this->timeout  = $timeout;
        $this->password = $password;

        $this->establishConnection();
    }

    function establishConnection()
    {
        $this->pconnect($this->host, (int) $this->port, (int) $this->timeout, getmypid());
        if ($this->password !== null) {
            $this->auth($this->password);
        }

        $this->setOption(Redis::OPT_PREFIX, self::$defaultNamespace);
    }

    public function prefix($namespace)
    {
        if (empty($namespace)) {
            $namespace = self::$defaultNamespace;
        }
        if (!str_contains($namespace, ':')) {
            $namespace .= ':';
        }
        self::$defaultNamespace = $namespace;

        $this->setOption(Redis::OPT_PREFIX, self::$defaultNamespace);
    }
}
