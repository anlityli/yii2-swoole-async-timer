<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2018/5/4
 * Time: 上午11:22
 */

namespace anlity\swooleAsyncTimer\src;


class SwooleClient
{
    public $option = [
        'host' => '',
        'port' => '',
        'timeout' => 30,
        'data' => '',
    ];

    private $_client;
    private $_errors = [];

    public function __construct()
    {
    }

    public function setOption($attr, $value){
        $this->option[$attr] = $value;
    }

    /**
     * 发送请求
     * @return bool|string
     * @throws \Exception
     */
    public function post(){
        $this->_client = new SWebSocket($this->option['host'], $this->option['port']);
        if (!$this->_client->connect()) {
            $this->addError('connect', "服务器连接失败. Error: {$this->_client->errCode}");
            return false;
        }
        $this->_client->send($this->option['data']);
        $result = $this->_client->recv();
        $this->_client->disconnect();
        if($result === false){
            $this->addError('send', "参数发送失败，Error:{$this->_client->errCode}");
        }
        return $result;
    }

    /**
     * @param $attr
     * @param $message
     */
    public function addError($attr, $message){
        $this->_errors[$attr][] = $message;
    }

    /**
     * @return array
     */
    public function getError(){
        return $this->_errors;
    }

}