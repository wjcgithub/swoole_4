<?php
$serv = new swoole_server("127.0.0.1", 9501);
$serv->on('receive', function ($serv, $fd, $from_id, $data){
	// die;
	// sleep(100);
	$i=2;
	while($i>1)
	    {
	        $i --;
	        sleep(5);
	    }

	$serv->send($fd, 'Swoole: '.$data);
});

$serv->start();