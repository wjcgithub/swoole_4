<?php
require_once "waitgroup.php";

use Swoole\Coroutine\Http\Client;

//此方法记录执行时间
function timediff($time)
{
    return microtime(true) - $time;
}

//创建http server
$http = new Swoole\Http\Server("0.0.0.0", 9501);
$http->set([
    //"daemonize" => true,
    "worker_num" => 1,
]);
$http->on('request', function ($request, $response) {

    //浏览器会自动发起这个请求，这也是很多人碰到的一个问题：
    //为什么我浏览器打开网站，收到了两个请求?
    if ($request->server['path_info'] == '/favicon.ico') {
        $response->end('');
        return;
    }

    $time = microtime(true);
    $response->header("content-type", "text/html; charset=UTF-8");
    //定义一个数组，用于存储结果,方便统一输出
    $result = [];
    $result[] = "1. 接受请求，此处被执行, 第" . __LINE__ . "行， 时间" . $time . "<br/>";


    $wg = new waitgroup();


    //加入wait计数
    $wg->add();
    //启动第一个协程
    go(function () use ($response, $wg, &$result) {
        $time = microtime(true);
        $result[] = "2. 进入第一个协程，发起http请求taobao, 第" . __LINE__ . "行, 时间:" . $time . "<br/>";

        //启动一个协程客户端client，请求淘宝首页
        $cli = new Client('www.taobao.com', 443, true);
        $cli->setHeaders([
            'Host' => "www.taobao.com",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $cli->set(['timeout' => 1]);
        //调用get方法，协程挂起，
        $cli->get('/index.php');
        //会等待i/o数据返回，执行wg的done方法，表示协程数据已返回
        $result[] = "7. get回taobao数据，唤起协程，此处被执行, 第" . __LINE__ . "行, 执行时间" . timediff($time) . "<br/>";
        $cli->close();

        //放在协程的最后执行
        $wg->done();
    });
    //上面get挂起协程后，后立马执行这一行
    $result[] = "3 cli->get时挂起协程了，此处被执行,不会被阻塞, 第" . __LINE__ . "行, 时间:" . microtime(true) . "<br/>";

    //加入wait计数
    $wg->add();
    //启动第二个协程
    go(function () use ($response, $wg, &$result) {
        $time = microtime(true);
        $result[] = "4. 进入第二个协程，发起http请求baidu, 第" . __LINE__ . "行, 时间:" . $time . "<br/>";
        //启动一个协程客户端client，请求百度首页
        $cli = new Client('www.baidu.com', 443, true);
        $cli->setHeaders([
            'Host' => "www.baidu.com",
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip',
        ]);
        $cli->set(['timeout' => 1]);
        //调用get方法，协程挂起，
        $cli->get('/index.php');
        //会等待i/o数据返回，执行wg的done方法，表示协程数据已返回
        $result[] = "6. get回baidu数据，唤起协程，此处被执行，正常这个先返回，因为ping百度更快，说明两个协程也是并发执行的, 第" . __LINE__ . "行, 执行时间" . timediff($time) . "<br/>";
        $cli->close();

        //放在协程的最后执行
        $wg->done();
    });
    //第二个协程get时挂起，执行到这一步
    $result[] = "5 cli->get时挂起协程了，此处被执行,不会被阻塞, 第" . __LINE__ . "行, 时间:" . microtime(true) . "<br/>";

    //堵塞中，直到所有的协程都执行调用done, 才会继续往下执行
    $wg->wait();
    $result[] = "总执行时间" . timediff($time) . "， 可看出约等于最长请求的时间而不是所有时间之和，协程间是真正并发执行的";
    $response->end(implode("<br/>", $result));
});
$http->start();