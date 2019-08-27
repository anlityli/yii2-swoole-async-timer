<?php
/**
 * 异步组件
 * $Id: SwooleAsyncComponent.php 9507 2016-09-29 06:48:44Z mevyen $
 * $Date: 2016-09-29 14:48:44 +0800 (Wed, 07 Sep 2016) $
 * $Author: mevyen $
 */
namespace anlity\swooleAsyncTimer;

use anlity\swooleAsyncTimer\src\SocketSecurity;
use anlity\swooleAsyncTimer\src\SwooleClient;
use Yii;
use anlity\swooleAsyncTimer\src\SCurl;
use yii\helpers\Json;

class SwooleAsyncTimerComponent extends \yii\base\Component implements SocketInterface
{

    public $swooleServer;

    /**
     * 获取服务端对象
     * @return mixed
     */
    public function getSwooleServer(){
        return $this->swooleServer;
    }

    /**
     * 异步执行入口
     * $data.data 定义需要执行的任务列表，其中如果指定多个任务(以数组形式),则server将顺序执行
     * $data.finish 定义了data中的任务执行完成后的回调任务，执行方式同$data.data
     * @param  [json] $data 结构如下
     * [
     *     'data' => [
     *         [
     *             'a' => 'test1/mail1' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test2/mail2' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ],
     *     'finish' => [
     *         [
     *             'a' => 'test3/mail3' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ],
     *         [
     *             'a' => 'test4/mail4' #要执行的console控制器和action
     *             'p' => ['p1','p2','p3'] // action参数列表
     *         ]
     *     ]
     * ]
     * @return [type]       [description]
     */
    public function async($data)
    {
        $data = $this->paresData($data);
        $data = ['type'=>'async', 'data'=>$data];
        return $this->requestServer($data);
    }

    /**
     * 用于从页面端实现webSocket推送消息
     * @param $fd
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function pushMsg($fd, $data){
        if(!$fd){
            return false;
        }
        $data = $this->paresData($data);
        $data = ['type'=>'pushMsg', 'fd' => $fd, 'data'=>$data];
        return $this->requestServer($data);
    }

    /**
     * 用于从页面端实现webSocket推送消息给所有已连接的会员
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function pushMsgAll($data){
        $data = $this->paresData($data);
        $data = ['type'=>'pushMsgAll', 'data'=>$data];
        return $this->requestServer($data);
    }

    /**
     * 从服务端的cli直接推送消息到客户端
     * @param $fd
     * @param $data
     * @return bool
     */
    public function pushMsgByCli($fd, $data){
        if(!$fd){
            return false;
        }
        $data = $this->paresData($data);
        return $this->swooleServer->push($fd, $data);
    }

    /**
     * 广播发送消息
     * @param $data
     */
    public function pushMsgAllByCli($data){
        $data = $this->paresData($data);
        foreach($this->swooleServer->connections as $fd){
            $this->swooleServer->push($fd, $data);
        }
    }

    /**
     * 请求服务端
     * @param $data
     * @return bool
     * @throws \Exception
     */
    public function requestServer($data){
        $settings = Yii::$app->params['swooleAsyncTimer'];
        $socketSecurity = new SocketSecurity($settings);
        $data = $socketSecurity->paramsFormat($data);
        if($settings['sender_client'] == 'swoole'){
            try {
                $client = new SwooleClient();
                $client->setOption('host', $settings['host']);
                $client->setOption('port', $settings['port']);
                $client->setOption('timeout', $settings['client_timeout']);
                $client->setOption('data', Json::encode($data));
                $response = $client->post();
            } catch (\Exception $e){
                $response = false;
            }
        } else {
            $client = new SCurl();
            $client->setOption(CURLOPT_POSTFIELDS, $data);
            $client->setOption(CURLOPT_TIMEOUT, $settings['client_timeout']);
            $response = $client->post("http://".$settings['host'].":".$settings['port']);
        }
        if($response === false){
            return false;
        }
        if($response === 'false'){
            return false;
        }
        return true;
    }

    /**
     * 处理数据
     * @param $data
     * @return string
     */
    public function paresData($data){
        if(!is_string($data)){
            $data = Json::encode($data);
        }
        return $data;
    }

    /**
     * swoole进程服务开始时的回调
     * @param $server
     * @param $workerId
     */
    public function onWorkerStart($server, $workerId){
    }

    /**
     * swoole进程服务结束时的回调
     * @param $server
     * @param $workerId
     */
    public function onWorkerStop($server, $workerId){
    }

    /**
     * swoole进程退出时的回调
     * @param $server
     * @param $workerId
     */
    public function onWorkerExit($server, $workerId){
    }

    /**
     * 需继承此方法，用于定时器的回调方法
     * @param $timerId
     * @param $server
     */
    public function timerCallback($timerId, $server){

    }

    /**
     * 需继承此方法，用于websocket的握手记录fd
     * @param $fd
     */
    public function onOpen($fd){
    }

    /**
     * 需继承此方法，用于websocket的清除fd
     * @param $fd
     */
    public function onClose($fd){

    }

    /**
     * 需继承此方法，用于websocket的接受客户端消息
     * @param $fd
     * @param $data
     */
    public function onMessage($fd, $data){

    }
    
}