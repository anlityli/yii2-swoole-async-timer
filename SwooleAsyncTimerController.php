<?php
/**
 * yii2 基于swoole的异步处理
 * $Id: SwooleAsyncController.php 9507 2016-09-29 06:48:44Z mevyen $
 * $Date: 2016-09-29 14:48:44 +0800 (Wed, 07 Sep 2016) $
 * $Author: mevyen $
 */
namespace anlity\swooleAsyncTimer;

use Yii;
use yii\console\Controller;
use anlity\swooleAsyncTimer\src\SwooleService;

class SwooleAsyncTimerController extends Controller {

    public $versionNumber = '0.9.1';
    /**
     * 存储swooleAsync配置中的所有配置项
     * @var array
     */
    public $settings = [];
    /**
     * 默认controller
     * @var string
     */
    public $defaultAction = 'run';

    /**
     * 强制执行的选项
     * @var
     */
    public $force;

    public $version;

    /**
     * 接收force选项
     * @param string $actionID
     * @return array|string[]
     */
    public function options($actionID)
    {
        return ['force', 'version'];
    }

    /**
     * 设置别名
     * @return array
     */
    public function optionAliases()
    {
        return [
            'f' => 'force',
            'v' => 'version',
        ];
    }


    /**
     * 初始化
     * @throws Yii\base\ErrorException
     */
    public function init() {

        parent::init();
        $this->prepareSettings();

    }

    /**
     * 默认执行的action
     */
    public function actionDefault(){
        if($this->version){
            echo($this->versionNumber.PHP_EOL);
        }
    }

    /**
     * 初始化配置信息
     * @throws Yii\base\ErrorException
     */
    protected function prepareSettings()
    {
        $runtimePath = Yii::$app->getRuntimePath();
        $this->settings = [
            'host'              => '127.0.0.1',
            'port'              => '9512',
            'process_name'      => 'swooleServ',
            'with_timer'        => true,
            'timer_interval'    => 30000,
            'open_tcp_nodelay'  => '1',
            'daemonize'         => '1',
            'worker_num'        => '2',
            'task_worker_num'   => '2',
            'task_max_request'  => '10000',
            'task_enable_coroutine'  => true,
            'debug'             => true,
            'pidfile'           => $runtimePath.'/tmp/yii2-swoole-async-timer.pid',
            'log_dir'           => $runtimePath.'/yii2-swoole-async-timer/log',
            'task_tmpdir'       => $runtimePath.'/yii2-swoole-async-timer/task',
            'log_file'          => $runtimePath.'/yii2-swoole-async-timer/log/http.log',
            'log_size'          => 204800000,
            'restartBeforeStartCallback'   => null, //重启服务时的回调方法，重启后并开始服务前的回调
            'restartAfterStartCallback'   => null, //重启服务时的回调方法，重启后并开始服务后的回调
        ];
        try {
            $settings = Yii::$app->params['swooleAsyncTimer'];
        }catch (yii\base\ErrorException $e) {
            throw new yii\base\ErrorException('Empty param swooleAsyncTimer in params. ',8);
        }

        $this->settings = yii\helpers\ArrayHelper::merge(
            $this->settings,
            $settings
        );
    }

    /**
     * 启动服务
     * @param string $mode
     */
    public function actionRun($mode='start'){
        $swooleService = new SwooleService($this->settings,Yii::$app, $this);
        switch ($mode) {
            case 'start':
                $swooleService->serviceStart();
                break;
            case 'restart':
                $swooleService->serviceStop(!!$this->force, function() use ($swooleService){
                    $restartBeforeStartCallback = $this->settings['restartBeforeStartCallback'];
                    if(!is_null($restartBeforeStartCallback) && ($restartBeforeStartCallback instanceof \Closure || is_callable($restartBeforeStartCallback))){
                        $restartBeforeStartCallback($swooleService, $this);
                    }
                    $swooleService->serviceStart();
                    $restartAfterStartCallback = $this->settings['restartAfterStartCallback'];
                    if(!is_null($restartAfterStartCallback) && ($restartAfterStartCallback instanceof \Closure || is_callable($restartAfterStartCallback))){
                        $restartAfterStartCallback($swooleService, $this);
                    }
                });
                break;
            case 'stop':
                $swooleService->serviceStop(!!$this->force);
                break;
            case 'stats':
                $swooleService->serviceStats();
                break;
            case 'list':
                $swooleService->serviceList();
                break;
            default:
                exit('error:参数错误');
                break;
        }
    }


}