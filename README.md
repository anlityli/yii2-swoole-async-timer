yii2-swoole-async-timer
=======================
用于异步处理任务和需要定时器完成的任务

Installation
------------

在mevyen的项目(代码中已保留原作者)的基础上加了一个定时器的功能，也就是打开swoole服务的时候开启一个定时器，来解决一些需要定时完成的任务，2018年4月9日加入了WebSocket的功能，服务端改为了WebSocket，可以监听客户端发来的消息，和向客户端发送消息。

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
        'sender_client'    => 'swoole',         //请求服务端的客户端方式(swoole|curl)
                'auth_key'          => 'xxxxxxxxxxxxxxx', //授权密钥
                'max_time_diff'      => 0,              //请求服务端允许的最大时间差
        'debug'            => true,             //是否开启调试模式
        'with_timer'       => true,            //是否使用定时器
        'timer_interval'   => 5000,            //定时器时间间隔
        'log_size'         => 204800000, 			 //运行时日志 单个文件大小
        'log_dir'          => dirname(__DIR__).'/runtime/logs',			 //运行时日志 存放目录
    ]
];
```

2、console的main.php文件引入新增的配置文件，配置文件参考 params-swoole-default.php
```php
$params = array_merge(
    // ...
    require __DIR__ . '/../../common/config/params-swoole.php',
    // ...
);
```

3、在站点主配置文件(main.php)controllerMap
```php
'controllerMap' => [
    'swoole_server' => [
        'class' => 'anlity\swooleAsyncTimer\SwooleAsyncTimerController',
    ],
],
```

4、在站点主配置文件(main.php)中增加components
```php
'components' => [
    'swooleAsyncTimer' => [
        'class' => 'common\components\SwooleAsyncTimer',
    ]
]
```

5、从目录common/components/新建SwooleAsyncTimer类，类内容：
```php
<?php
namespace common\components;

use anlity\swooleAsyncTimer\SocketInterface;
use anlity\swooleAsyncTimer\SwooleAsyncTimerComponent;

class SwooleAsyncTimer extends SwooleAsyncTimerComponent implements SocketInterface {

    public function timerCallback($timerId, $server){
        // 定时器的回调逻辑
    }

    public function onWorkerStart($server, $workerId){
    }

    public function onWorkerStop($server, $workerId){
    }

    public function onOpen($fd){
        // 与客户端握手时的逻辑，可以把$fd写入到session或者缓存中
    }


    public function onClose($fd){
        // 与客户端断开连接时的逻辑
    }


    public function onMessage($fd, $data){
        // 收到客户端的消息的逻辑
    }
}
```

6、服务管理
```php
//启动
./yii swoole_server/run start
 
//重启
./yii swoole_server/run restart

//停止
./yii swoole_server/run stop

//查看状态
./yii swoole_server/run stats

//查看进程列表
./yii swoole_server/run list
```

7、执行异步任务可以
````php
<?php

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
		\Yii::$app->SwooleAsyncTimer->async(json_encode($data));
	}

	public function actionMail($a='',$b=''){
		echo $a.'-'.$b;
	}  
}
````

8、给客户端发送消息可以
````php
<?php

class TestController extends Controller 
{  
	public function actionPushMsg(){
		$fd = 1;
		$data = [];
		\Yii::$app->SwooleAsyncTimer->pushMsg($fd, $data);
	}

}
````

9、从服务端给webSocket客户端发送消息
````php
<?php

class TestController extends Controller 
{  
	public function actionPushMsg(){
		$fd = 1;
		$data = [];
		\Yii::$app->SwooleAsyncTimer->pushMsgByCli($fd, $data);
	}

}
````

10、无人值守
````shell
* * * * * /path/to/yii/application/yii swoole_server/run start >> /var/log/console-app.log 2>&1
````