<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 18-12-5
 * Time: 下午5:24
 */
require_once "MysqlPool.php";
$config = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'user'=>'root',
    'password'=>'brave',
    'database'=>'xin_dtq',
    'timeout'=>0.5,  //数据库链接超时时间
    'charset'=>'utf8mb4',
    'strict_type'=>true,
    'pool_size'=>3,
    'pool_get_timeout' =>0.5  //当在此时间内未获取到一个ie链接, 会立即返回, (表示所有的连接都已在使用中）
];

//创建http server
$http = new Swoole\Http\Server('0.0.0.0', 9501);
$http->set([
//    'daemonize'=>true,
    'worker_num' => 1,
    'log_level' => 0,
]);

$http->on('WorkerStart', function ($serv, $woker_id) use ($config){
    //当worker启动的时候每个进程都初始化链接池, 在onRequest中可以直接使用
    try{
        MysqlPool::getInstance($config);
    }catch (\Exception $e){
        //初始化异常,关闭服务
        echo $e->getMessage().'--'.$e->getLine().PHP_EOL;
        $serv->shutdown();
    } catch (\Throwable $throwable) {
        //初始化异常,关闭服务
        echo $throwable->getMessage().'--'.$throwable->getLine().'--'.$throwable->getFile().PHP_EOL;
        $serv->shutdown();
    }
});

$http->on('request', function($request, $response){
    //忽略ico访问
    if ($request->server['path_info'] == '/favicon.ico') {
        $response->end('');
        return;
    }

    //获取数据库
    if ($request->server['path_info'] == '/list') {
        go(function () use ($request, $response) {
            //从池子中获取一个实例
            try{
                $pool = MysqlPool::getInstance();
                $mysql = $pool->get();
                defer(function () use ($mysql){
                    //利用defer, 可以达到协成执行完毕归还mysql到链接池
                    //好处是:可能因为业务代码很长, 导致乱用或者忘记把资源归还
                    MysqlPool::getInstance()->put($mysql);
                    //todo
                    echo "defer: list pid->".posix_getpid()."当前可用链接数: ".MysqlPool::getInstance()->getLength().PHP_EOL;
                });

                $result = $mysql->query('select * from groups');
                $response->end(json_encode($result));
            }catch (\Exception $e){
                $response->end($e->getMessage());
            }
        });
    }

    //模拟timeout, 浏览器打开4个tab，都请求 http://127.0.0.1:9501/timeout，前三个应该是等10秒出结果，第四个500ms后出超时结果
    //ps: chrome浏览器，需要加一个随机数，http://127.0.0.1:9501/timeout?t=0, http://127.0.0.1:9501/timeout?t=1, 因为chrome会对完全一样的url做并发请求限制
    echo "pid->".posix_getpid()."get request:".time().PHP_EOL;
    if ($request->server['path_info'] == '/timeout') {
        go(function () use ($request, $response) {
            //从池子中获取一个实例
            try {

                $pool = MysqlPool::getInstance();
                echo "pid->".posix_getpid()."当前可用连接数：" . $pool->getLength() . PHP_EOL;
                $mysql = $pool->get();
                echo "pid->".posix_getpid()."当前可用连接数：" . $pool->getLength() . PHP_EOL;
                defer(function () use ($mysql) {
                    //协程执行完成，归还$mysql到连接池
                    MysqlPool::getInstance()->put($mysql);
                    echo "defer: timeout pid->".posix_getpid()."当前可用连接数：" . MysqlPool::getInstance()->getLength() . PHP_EOL;
                });
                $result = $mysql->query("select * from test");
                \Swoole\Coroutine::sleep(30); //sleep 10秒,模拟耗时操作
//                sleep(30);
                $response->end(json_encode($result));
            } catch (\Exception $e) {
                $response->end($e->getMessage().'--'.$e->getLine());
            }
        });
        return;
    }

});

$http->start();