<?php
$serv = new \swoole\http\server("192.168.0.101", 9501, SWOOLE_BASE);

$serv->on('request', function($req, $resp) {
	$chan = new chan(2);

	go(function () use ($chan){
		$cli = new Swoole\Coroutine\Http\Client('www.baidu.com', 443, true);
		$cli->set(['timeout'=>10]);
		$cli->setHeaders([
			'Host'=>'www.baidu.com',
			'User-Agent'=> 'Chrome/111',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip'
		]);

		$ret = $cli->get('/');
		$chan->push(['www.baidu.com' => substr(trim(strip_tags($cli->body)), 0, true)]);
	});

    go(function () use ($chan){
        $cli = new Swoole\Coroutine\Http\Client('www.taobao.com', 443, true);
        $cli->set(['timeout'=>10]);
        $cli->setHeaders([
            'Host'=>'www.baidu.com',
            'User-Agent'=> 'Chrome/111',
            'Accept' => 'text/html,application/xhtml+xml,application/xml',
            'Accept-Encoding' => 'gzip'
        ]);

        $ret = $cli->get('/');
        $chan->push(['www.taobao.com' => substr(trim(strip_tags($cli->body)), 0, true)]);
    });

    $result = [];
    for ($i=0; $i<2; $i++) {
        $result += $chan->pop();
    }


    $resp->header('Content-type', 'text/html;charset=utf-8');
    $resp->end(var_export($result, true));
});

$serv->start();