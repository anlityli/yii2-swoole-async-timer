<?php
/**
 * Swoole 实现的 server,用来处理异步多进程任务
 * $Id: SServer.php 9507 2016-09-29 06:48:44Z mevyen $
 * $Date: 2016-09-29 14:48:44 +0800 (Wed, 07 Sep 2016) $
 * $Author: mevyen $
 * $Modifier: anlity $
 */

namespace anlity\swooleAsyncTimer\src;

use SebastianBergmann\Timer\Timer;
use yii\base\Exception;
use yii\helpers\Json;

class SServer {
    /**
     * swoole server 实例
     * @var null|swoole_server
     */
    protected $server = null;

    /**
     * swoole 配置
     * @var array
     */
    private $setting = [];

    /**
     * Yii::$app 对象
     * @var array
     */
    private $app = null;

    /**
     * 定时器ID
     * @var
     */
    private $_timerId = false;

    private $_swooleController;

    /**
     * SServer constructor.
     * @param $setting
     * @param $app
     * @param $swooleController
     */
    public function __construct($setting,$app, &$swooleController){
        $this->setting = $setting;
        $this->app = $app;
        $this->_swooleController = $swooleController;
    }

    /**
     * 设置swoole进程名称
     * @param string $name swoole进程名称
     */
    private function setProcessName($name){
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($name);
        } else {
            if (function_exists('swoole_set_process_name')) {
                @swoole_set_process_name($name);
            } else {
                trigger_error(__METHOD__. " failed.require cli_set_process_title or swoole_set_process_name.");
            }
        }
    }

    /**
     * 运行服务
     * @return mixed
     */
    public function run(){

        $this->server = $this->app->swooleAsyncTimer->swooleServer = new \swoole_websocket_server($this->setting['host'], $this->setting['port']);
        $this->server->set($this->setting);
        //回调函数
        $call = [
            'start',
            'workerStart',
            'managerStart',
            'open',
            'message',
            //'receive',
            'request',
            'task',
            'finish',
            'close',
            'workerStop',
            'workerExit',
            'shutdown',
        ];
        //事件回调函数绑定
        foreach ($call as $v) {
            $m = 'on' . ucfirst($v);
            if($this->setting['task_enable_coroutine'] && $v == 'task'){
                $m = 'onTaskEnableCoroutine';
            }
            if (method_exists($this, $m)) {
                $this->server->on($v, [$this, $m]);
            }
        }

        echo "服务成功启动" . PHP_EOL;
        echo "服务运行名称:{$this->setting['process_name']}" . PHP_EOL;
        echo "服务运行端口:{$this->setting['host']}:{$this->setting['port']}" . PHP_EOL;

        return $this->server->start();
    }

    /**
     * @param $server
     * @return bool
     */
    public function onStart($server){
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_server master worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-master');
        //记录进程id,脚本实现自动重启
        $pid = "{$this->server->master_pid}\n{$this->server->manager_pid}";
        file_put_contents($this->setting['pidfile'], $pid);
        return true;
    }

    /**
     * @param $server
     */
    public function onManagerStart($server){
        echo '[' . date('Y-m-d H:i:s') . "]\t swoole_server manager worker start\n";
        $this->setProcessName($server->setting['process_name'] . '-manager');
    }

    /**
     * @param $server
     * @param $request
     */
    public function onOpen($server, $request){
        $this->app->swooleAsyncTimer->onOpen($request->fd);
    }

    /**
     * @param $server
     * @param $frame
     * @return mixed
     */
    public function onMessage($server, $frame){
        // 检测服务是否开启
        if($frame->data == 'stats'){
            $websocket_number['websocket_number'] = count($server->connection_list(0,100));
            array_push($websocket_number,$server->stats());
            return $server->push($frame->fd,Json::encode($websocket_number));
        }
        else {
            $data = Json::decode($frame->data);
            if(isset($data['type'])){
                $socketSecurity = new SocketSecurity($this->setting);
                if(!$socketSecurity->checkSignature($data['signature'], $data)){
                    return $server->push($frame->fd, 'false');
                }
                // 处理异步任务
                if($data['type'] == 'async'){
                    $server->task($data['data']);
                    return $server->push($frame->fd, 'to task success!');
                }
                // 处理消息推送任务
                elseif ($data['type'] == 'pushMsg'){
                    if($server->push($data['fd'], $data['data'])){
                        return $server->push($frame->fd, 'to push message success!');
                    }
                }
                // 处理消息推送全部连接
                elseif ($data['type'] == 'pushMsgAll'){
                    $pushMsgAllResult = true;
                    foreach($server->connections as $fd){
                        if(!$server->push($fd, $data['data'])) $pushMsgAllResult = false;
                    }
                    if($pushMsgAllResult){
                        return $server->push($frame->fd, 'to push message success!');
                    }
                } else {
                    echo('类型失败'.PHP_EOL);
                }
                return $server->push($frame->fd, 'false');
            } else {
                return $this->app->swooleAsyncTimer->onMessage($frame->fd, $frame->data);
            }
        }
    }

    /**
     * @param $server
     * @param $fd
     */
    public function onClose($server, $fd){
        //unlink($this->setting['pidfile']);
        $this->app->swooleAsyncTimer->onClose($fd);
        //echo '[' . date('Y-m-d H:i:s') . "]\t swoole_server shutdown\n";
    }

    /**
     * @param $server
     * @param $workerId
     */
    public function onWorkerStart($server, $workerId){
        if ($workerId >= $this->setting['worker_num']) {
            $this->setProcessName($server->setting['process_name'] . '-task');
        } else {
            $this->setProcessName($server->setting['process_name'] . '-event');
        }
        //记录进程id,脚本实现自动重启
        $pid = "\n{$this->server->worker_pid}";
        file_put_contents($this->setting['pidfile'], $pid, FILE_APPEND);
        // 生成一个定时器
        if ($this->setting['with_timer'] && !$server->taskworker && $workerId == 0) {
            $server->tick($this->setting['timer_interval'], function($timerId) use($server){
                $this->_timerId = $timerId;
                //$this->_swooleController->timerCallback($timerId, $server);
                $this->app->swooleAsyncTimer->timerCallback($timerId, $server);
            });
        }
        $this->app->swooleAsyncTimer->onWorkerStart($server, $workerId);
    }

    /**
     * @param $server
     * @param $workerId
     */
    public function onWorkerStop($server, $workerId){
        $this->app->swooleAsyncTimer->onWorkerStop($server, $workerId);
        echo '['. date('Y-m-d H:i:s') ."]\t swoole_server[{$server->setting['process_name']}  worker:{$workerId} shutdown\n";
    }

    /**
     * @param $server
     * @param $workerId
     */
    public function onWorkerExit($server, $workerId){
        // 清空定时器
        foreach(\Swoole\Timer::list() as $timerId){
            \Swoole\Timer::clear($timerId);
        }
        $this->app->swooleAsyncTimer->onWorkerExit($server, $workerId);
        echo '['. date('Y-m-d H:i:s') ."]\t swoole_server[{$server->setting['process_name']}  worker:{$workerId} exit\n";
    }

    // /**
    //  * 处理请求
    //  * @param $request
    //  * @param $response
    //  *
    //  * @return mixed
    //  */
    // public function onReceive($server, $fd, $from_id, $data){ 
    //     if($data == 'stats'){
    //         return $this->server->send($fd,var_export($this->server->stats(),true),$from_id);
    //     }
    //     $this->server->task($data); 
    //     return true;

    // }
    /**
     * 请求处理
     * @param $request
     * @param $response
     *
     * @return mixed
     */
    public function onRequest($request, $response)
    { 
        //获取swoole服务的当前状态
        if (isset($request->post['cmd']) && $request->post['cmd'] == 'status') {
            return $response->end(Json::encode($this->server->stats()));
        } else {
            if(isset($request->post['type'])){
                $socketSecurity = new SocketSecurity($this->setting);
                if(!$socketSecurity->checkSignature($request->post['signature'], $request->post)){
                    return $response->end('false');
                }
                if($request->post['type'] == 'async'){
                    $data = $request->post['data'];
                    $this->server->task($data);
                }
                elseif($request->post['type'] == 'pushMsg'){
                    $this->server->push($request->post['fd'], $request->post['data']);
                }
                elseif($request->post['type'] == 'pushMsgAll'){
                    foreach($this->server->connections as $fd){
                        $this->server->push($fd, $request->post['data']);
                    }
                }
                else {
                    return $response->end('false');
                }
            } else {
                return $response->end('false');
            }
        }
        $out = '[' . date('Y-m-d H:i:s') . '] ' . Json::encode($request) . PHP_EOL;
        $response->end($out);

        return true;
    }

    /**
     * 任务处理
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return array|bool|mixed|void
     */
    public function onTask($serv, $task_id, $from_id, $data){
        $this->logger('[task data] '.$data);
        $data = $this->parseData($data);
        if($data === false){
            return;
        }
        foreach ($data['data'] as $param) {
            if(!isset($param['a']) || empty($param['a'])){
                continue;
            }
            $action = $param["a"];
            $params = [];
            if(isset($param['p'])){
                $params = $param['p'];
                if(!is_array($params)){
                    $params = [strval($params)];
                }
            }
            try{
                //print_r($action.PHP_EOL);
                $parts = $this->app->createController($action);
                if (is_array($parts)) {
                    $res = $this->app->runAction($action,$params);
                    $this->logger('[task result] '.var_export($res,true));
                }
                if($this->app->db && $this->app->db->isActive){
                    $this->app->db->close();
                }
            }catch(Exception $e){
                $this->logger($e->getMessage());
            }
        }
        return $data;
    }

    /**
     * 开启了 task_enable_coroutine 的task回调函数
     * @param $serv
     * @param \Swoole\Server\Task $task
     */
    public function onTaskEnableCoroutine($serv, \Swoole\Server\Task $task){
        $this->logger('[task data] '.$task->data);
        $data = $this->parseData($task->data);
        if($data === false){
            return;
        }
        foreach ($data['data'] as $param) {
            if(!isset($param['a']) || empty($param['a'])){
                continue;
            }
            $action = $param["a"];
            $params = [];
            if(isset($param['p'])){
                $params = $param['p'];
                if(!is_array($params)){
                    $params = [strval($params)];
                }
            }
            try{
                //print_r($action.PHP_EOL);
                $parts = $this->app->createController($action);
                if (is_array($parts)) {
                    $res = $this->app->runAction($action,$params);
                    $this->logger('[task result] '.var_export($res,true));
                }
                if($this->app->db && $this->app->db->isActive){
                    $this->app->db->close();
                }
            }catch(Exception $e){
                $this->logger($e->getMessage());
            }
        }
        $task->finish($data);
    }

    /**
     * 解析data对象
     * @param $data
     * @return array|bool|mixed
     */
    private function parseData($data){
        if(is_string($data)){
            $data = Json::decode($data);
        }
        $data = $data ?: [];
        if(!isset($data["data"]) || empty($data["data"])){
            return false;
        }
        return $data;

    }

    /**
     * 解析onfinish数据
     * @param $data
     * @return bool|string
     */
    private function genFinishData($data){
        if(!isset($data['finish']) || !is_array($data['finish'])){
            return false;
        }
        return Json::encode(['data'=>$data['finish']]);
    }

    /**
     * 任务结束回调函数
     * @param $server
     * @param $taskId
     * @param $data
     * @return bool
     */
    public function onFinish($server, $taskId, $data){

        $data = $this->genFinishData($data);
        if($data !== false ){
            return $this->server->task($data);
        }

        return true;

    }

    /**
     *
     */
    public function onShutdown(){
        echo '[' . date('Y-m-d H:i:s') . "]\t server shutdown 关闭服务完成\n";
        unlink($this->setting['pidfile']);
    }

    /**
     * 记录日志 日志文件名为当前年月（date("Y-m")）
     * @param $msg
     * @param string $logfile
     */
    public function logger($msg,$logfile='') {
        if (empty($msg)) {
            return;
        }
        if (!$this->setting['debug']){
            return;
        }
        if (!is_string($msg)) {
            if(is_object($msg) || is_array($msg)){
                $msg = var_export($msg, true);
            } else {
                $msg = '未知错误';
            }
        }
        //日志内容
        $msg = '['. date('Y-m-d H:i:s') .'] '. $msg . PHP_EOL;
        //日志文件大小
        $maxSize = $this->setting['log_size'];
        //日志文件位置
        $file = $logfile ?: $this->setting['log_dir']."/".date('Y-m').".log";
        //切割日志
        if (file_exists($file) && filesize($file) >= $maxSize) {
            $bak = $file.'-'.time();
            if (!rename($file, $bak)) {
                error_log("rename file:{$file} to {$bak} failed", 3, $file);
            }
        }
        error_log($msg, 3, $file);
    }
}

