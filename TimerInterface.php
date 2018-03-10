<?php
/**
 * Created by PhpStorm.
 * User: anlity
 * Date: 2018/3/10
 * Time: 上午11:58
 */

namespace anlity\swooleAsyncTimer;


interface TimerInterface
{
    public function timerCallback($timerId, $server);
}