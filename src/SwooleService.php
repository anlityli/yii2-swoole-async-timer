<?php
/**
 * 服务管理脚本
 * $Id: SwooleService.php 9507 2016-09-29 06:48:44Z mevyen $
 * $Date: 2016-09-29 14:48:44 +0800 (Wed, 07 Sep 2016) $
 * $Author: mevyen $
 */
namespace anlity\swooleAsyncTimer\src;

use yii\helpers\Json;

class SwooleService{
    /**
     * 配置对象
     * @var array
     */
    protected $settings = [];
    /**
     * Yii::$app
     * @var null
     */
    private $app = null;

    private $swooleContorller = null;

    function __construct($settings,$app, &$swooleContorller){
        $this->check();
        $this->settings = $settings;
        $this->app = $app;
        $this->swooleContorller = $swooleContorller;
    }

    /**
     * [check description]
     * @return [type] [description]
     */
    private function check(){
        /**
        * 检测 PDO_MYSQL
        */
        if (!extension_loaded('pdo_mysql')) {
            exit('error:请安装PDO_MYSQL扩展' . PHP_EOL);
        }
        /**
        * 检查exec 函数是否启用
        */
        if (!function_exists('exec')) {
            exit('error:exec函数不可用' . PHP_EOL);
        }
        /**
        * 检查命令 lsof 命令是否存在
        */
        exec("whereis lsof", $out);
        if (strpos($out[0], "/usr/sbin/lsof") === false ) {
            exit('error:找不到lsof命令,请确保lsof在/usr/sbin下' . PHP_EOL);
        }
    }

    /**
     * 获取指定端口的服务占用列表
     * @param  [type] $port 端口号
     * @return [type]       [description]
     */
    private function bindPort($port) {
        $res = [];
        $cmd = "/usr/sbin/lsof -i :{$port}|awk '$1 != \"COMMAND\"  {print $1, $2, $9}'";
        exec($cmd, $out);
        if ($out) {
            foreach ($out as $v) {
                $a = explode(' ', $v);
                list($ip, $p) = explode(':', $a[2]);
                $res[$a[1]] = [
                    'cmd'  => $a[0],
                    'ip'   => $ip,
                    'port' => $p,
                ];
            }
        }
        return $res;
    }
    /**
     * 启动服务
     * @return [type] [description]
     */
    public function serviceStart(){

        $pidfile = $this->settings['pidfile'];
        $host = $this->settings['host'];
        $port = $this->settings['port'];

        $this->msg("服务正在启动...");

        if (!is_writable(dirname($pidfile))) {
            $this->error("pid文件需要写入权限");
        }
        if (file_exists($pidfile)) {
            $pid = explode("\n", file_get_contents($pidfile));
            $cmd = "ps ax | awk '{ print $1 }' | grep -e \"^{$pid[0]}$\"";
            exec($cmd, $out);
            if (!empty($out)) {
                $this->msg("[warning]:pid文件已存在,服务已经启动,进程id为:{$pid[0]}",true);
            } else {
                $this->msg("[warning]:pid文件已存在,可能是服务上次异常退出");
                unlink($pidfile);
            }
        }

        $bind = $this->bindPort($port);

        if ($bind) {
            foreach ($bind as $k => $v) {
                if ($v['ip'] == '*' || $v['ip'] == $host) {
                    $this->error("服务启动失败,{$host}:{$port}端口已经被进程ID:{$k}占用");
                }
            }
        }

        //启动
        $server = new SServer($this->settings,$this->app,$this->swooleContorller);
        $server->run();
        
    }

    /**
     * 查看服务状态
     * @param  [type] $host host
     * @param  [type] $port port
     * @return [type]       [description]
     */
    public function serviceStats(){

        $client = new \swoole_http_client($this->settings['host'], $this->settings['port']);
//        if (!$client->connect($this->settings['host'], $this->settings['port'], $this->settings['client_timeout'])){
//            exit("Error: connect server failed. code[{$client->errCode}]\n");
//        }
//        $client->send('stats');
//
//        echo $client->recv();
        $client->on('message', function ($cli, $frame){
            var_dump($frame);
            echo(PHP_EOL);
            $cli->close();
        });
        $client->upgrade('/', function ($cli){
            $cli->push('stats');
//            $cli->close();
        });
    }

    /**
     * 查看进程列表
     * @return [type] [description]
     */
    public function serviceList(){

        $cmd = "ps aux|grep " . $this->settings['process_name'] . "|grep -v grep|awk '{print $1, $2, $6, $8, $9, $11}'";
        exec($cmd, $out);

        if (empty($out)) {
            $this->msg("没有发现正在运行服务",true);
        }

        $this->msg("本机运行的服务进程列表:");
        $this->msg("USER PID RSS(kb) STAT START COMMAND");

        foreach ($out as $v) {
            $this->msg($v);
        }

    }

    /**
     * 停止服务
     * @param bool $isForce
     */
    public function serviceStop($isForce = false){

        $pidfile = $this->settings['pidfile'];

        $this->msg("正在停止服务...");

        if (!file_exists($pidfile)) {
            $this->error("pid文件:". $pidfile ."不存在");
        }
        $pid = explode("\n", file_get_contents($pidfile));
        if($isForce && !empty($pid)){
            foreach($pid as $id){
                if($id){
                    $this->_kill($id);
                }
            }
        }
        if(!$isForce){
            if ($pid[0]) {
                $this->_kill($pid[0]);
            }
        }

        //确保停止服务后swoole-task-pid文件被删除
        if (file_exists($pidfile)) {
            unlink($pidfile);
        }

        $this->msg("服务已停止");

    }

    /**
     * 杀进程
     * @param $pid
     * @return bool
     */
    private function _kill($pid){
        $cmd = "kill {$pid}";
        exec($cmd, $sign);
        if(!$sign){
            return true;
        }
        do {
            $out = [];
            $c = "ps ax | awk '{ print $1 }' | grep -e \"^{$pid}$\"";
            exec($c, $out);
            if (empty($out)) {
                break;
            }else{
                exec("kill -9 {$pid}");
            }
        } while (true);
    }

    /**
     * [error description]
     * @param  [type] $msg [description]
     * @return [type]      [description]
     */
    private function msg($msg,$exit=false){

        if($exit){
            exit($msg . PHP_EOL);
        }else{
            echo $msg . PHP_EOL;
        }
    }    
    /**
     * [error description]
     * @param  [type] $msg [description]
     * @return [type]      [description]
     */
    private function error($msg){
        exit("[error]:".$msg . PHP_EOL);
    }
    
}


