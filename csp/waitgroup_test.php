<?php
/**
 * Created by PhpStorm.
 * User: evolution
 * Date: 18-12-4
 * Time: 下午8:36
 */
require_once "waitgroup.php";
use Swoole\Coroutine\Http\Client;

$status = "SUCCESS";

if ($status == 'FAILURE') {
    echo 1;
} else if ($status == 'SUCCESS') {
    echo 2;
} else if ($status == 'RETRY') {
    echo 3;
} else {
    echo 4;
}

die;


$wg = null;
//$sm = memory_get_peak_usage();
//$sm1 = memory_get_usage();
go(function () use (&$wg){
    $wg = new \waitgroup();
    $count = 10;
    for ($i=0; $i<$count; $i++){
        go(function () use ($wg) {
            $wg->add();
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
            $response = $cli->get('/index.php');
            $response = $cli->statusCode;
            //会等待i/o数据返回，执行wg的done方法，表示协程数据已返回
            $cli->close();
            //放在协程的最后执行
            $wg->done($response);
        });
    }
    $wg->wait();
});

//$em = memory_get_peak_usage();
//$em1 = memory_get_usage();
//echo "\r\n";
//echo ($em-$sm)/1024/1024;
//echo "\r\n";
//echo ($em1-$sm1)/1024/1024;
