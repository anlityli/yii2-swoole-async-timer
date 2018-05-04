<?php
/**
 * Created by PhpStorm.
 * User: Leo
 * Date: 2018/5/4
 * Time: 下午10:08
 */

namespace anlity\swooleAsyncTimer\src;


class SocketSecurity {

    public $setting;

    public function __construct($setting){
        $this->setting = $setting;
    }
    /**
     * 获取密钥
     * @return string
     */
    public function getAuthKey(){
        return $this->setting['auth_key'];
    }

    /**
     * 获取允许的时间差
     * @return string
     */
    public function getTimeDiff(){
        return $this->setting['max_time_diff'];
    }

    /**
     * 生成签名
     * @param array $params
     * @return array
     */
    public function paramsFormat(array $params){
        if(!isset($params['timestamp'])){
            $params['timestamp'] = time();
        }
        if(isset($params['signature'])){
            unset($params['signature']);
        }
        ksort($params);
        $string = '';
        foreach($params as $key=>$value){
            $string .= $key.'='.$value . '&';
        }
        $params['signature'] = sha1(trim($string,'&') . $this->getAuthKey());
        return $params;
    }

    /**
     * 验证签名
     * @param $signature
     * @param array $params
     * @return bool
     */
    public function checkSignature($signature, array $params){
        $params = $this->paramsFormat($params);
        if($params['signature'] !== $signature){
            return false;
        }
        $timeDiff = (int)$this->getTimeDiff();
        if($timeDiff > 0 && (time() -> $params['timestamp']) > $timeDiff){
            return false;
        }
        return true;
    }
}