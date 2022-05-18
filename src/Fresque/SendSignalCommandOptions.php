<?php

namespace Freelancehunt\Fresque;

/**
 * SendSignalCommandOptions Class
 */
class SendSignalCommandOptions
{
    public $title            = '';
    public $noWorkersMessage = '';
    public $allOption        = '';
    public $listTitle        = 'Workers list';
    public $selectMessage    = '';
    public $actionMessage    = '';
    public $workers          = [];
    public $signal           = '';
    public $successCallback;

    public function onSuccess($pid, $workerName)
    {
        $callback = $this->successCallback;

        return $callback($pid, $workerName);
    }

    public function getWorkersCount()
    {
        return count($this->workers);
    }
}
