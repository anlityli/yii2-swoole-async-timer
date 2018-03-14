yii2-swoole-async-timer
=======================
用于异步处理任务和需要定时器完成的任务

Installation
------------

在mevyen的项目(代码中已保留原作者)的基础上加了一个定时器的功能，也就是打开swoole服务的时候开启一个定时器，来解决一些需要定时完成的任务，。

安装

```
php composer.phar require --prefer-dist anlity/yii2-swoole-async-timer "*"
```

或者 添加

```
"anlity/yii2-swoole-async-timer": "*"
```

到你的 yii2 根目录 `composer.json` 文件里.


如何使用
-----

1、新增配置文件params-swoole.php

```php
return [
    'swooleAsyncTimer' => [
        'host'             => '127.0.0.1', 		//服务启动IP
        'port'             => '9512',      		//服务启动端口
        'process_name'     => 'swooleServ',		//服务进程名
        'open_tcp_nodelay' => '1',         		//启用open_tcp_nodelay
        'daemonize'        => '1',				//守护进程化
        'worker_num'       => '2',				//work进程数目
        'task_worker_num'  => '2',				//task进程的数量
        'task_max_request' => '10000',			//work进程最大处理的请求数
        'task_tmpdir'      => dirname(__DIR__).'/runtime/task',		 //设置task的数据临时目录
        'log_file'         => dirname(__DIR__).'/runtime/logs/swooleHttp.log', //指定swoole错误日志文件
        'client_timeout'   => '20',				 //client链接服务器时超时时间(s)
        'pidfile'          => '/tmp/y.pid', 		 //服务启动进程id文件保存位置

        //--以上配置项均来自swoole-server的同名配置，可随意参考swoole-server配置说明自主增删--
        'debug'            => true,             //是否开启调试模式
        'with_timer'       => true,            //是否使用定时器
        'timer_interval'   => 5000,            //定时器时间间隔
        'log_size'         => 204800000, 			 //运行时日志 单个文件大小
        'log_dir'          => dirname(__DIR__).'/runtime/logs',			 //运行时日志 存放目录
    ]
];
```

2、console的main.php文件引入新增的配置文件
```php
$params = array_merge(
    // ...
    require __DIR__ . '/../../common/config/params-swoole.php',
    // ...
);
```

3、在站点主配置文件(main.php)中增加components
```php
'components' => [
    'swooleasync' => [
        'class' => 'anlity\swooleAsyncTimer\SwooleAsyncTimerComponent',
    ]
]
```

4、console的控制器内容：
```php
<?php
namespace console\controllers;

use Yii;
use anlity\swooleAsyncTimer\SwooleAsyncTimerController;

class SwooleController extends SwooleAsyncTimerController
{
    
    public function timerCallback($timerId, $server)
    {
        // todo 这里是您的需要在定时器里完成的逻辑代码
    }
}
```

5、服务管理
```php
//启动
./yii swoole/run start
 
//重启
./yii swoole/run restart

//停止
./yii swoole/run stop

//查看状态
./yii swoole/run stats

//查看进程列表
./yii swoole/run list
```

6、执行异步任务可以
````php
<?php
namespace console\controllers;
use yii\console\Controller;

class TestController extends Controller 
{  
	public function actionSwooleasync(){
		$data = [
			"data"=>[
				[
					"a" => "test/mail",
					"p" => ["测试邮件1","测试邮件2"]
				],
				// ...
			],
			"finish" => [
				[
					"a" => "test/mail",
					"p" => ["测试邮件回调1","测试邮件回调2"]
				],
				// ...
			]
		];
		\Yii::$app->swooleasync->async(json_encode($data));
	}

	public function actionMail($a='',$b=''){
		echo $a.'-'.$b;
	}  
}
````

7、无人值守
````shell
* * * * * /path/to/yii/application/yii swooleasync/run start >> /var/log/console-app.log 2>&1
````