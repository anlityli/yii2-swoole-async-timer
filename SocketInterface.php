<?php
/**
 * Created by PhpStorm.
 * User: anlity
 * Date: 2018/3/10
 * Time: 上午11:58
 */

namespace anlity\swooleAsyncTimer;


interface SocketInterface
{
    public function timerCallback($timerId, $server);
    public function onWorkerStart($server, $workerId);
    public function onWorkerStop($server, $workerId);
    public function onWorkerExit($server, $workerId);
    public function onOpen($fd);
    public function onClose($fd);
    public function onMessage($fd, $data);
    public function onTaskRunActionStart($server, $workerId, $action, $params);
    public function onTaskRunActionFinish($server, $workerId, $action, $params);
    public function onTaskRunActionError($fd, $data, $action, $params, $errorMessage);
}