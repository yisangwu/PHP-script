<?php

/******************************
   TCp server
****************************/

// //创建Server对象，监听 127.0.0.1:9501端口
// $serv = new swoole_server("127.0.0.1", 9501); 

// //监听连接进入事件
// $serv->on('connect', function ($serv, $fd) {  
//     echo "Client: {$fd} Connect.\n";
// });

// //监听数据接收事件
// $serv->on('receive', function ($serv, $fd, $from_id, $data) {
//     $serv->send($fd, "Server: {$fd}, {$from_id} ".$data);
// });

// //监听连接关闭事件
// $serv->on('close', function ($serv, $fd) {
//     echo "Client: {$fd} Close.\n";
// });

// //启动服务器
// $serv->start(); 


// /******************************
//    UDP server
// ****************************/

// //创建Server对象，监听 127.0.0.1:9502端口，类型为SWOOLE_SOCK_UDP
// $serv = new swoole_server("127.0.0.1", 9502, SWOOLE_PROCESS, SWOOLE_SOCK_UDP); 

// //监听数据接收事件
// $serv->on('Packet', function ($serv, $data, $clientInfo) {
//     $serv->sendto($clientInfo['address'], $clientInfo['port'], "Server ".$data);
//     var_dump($clientInfo);
// });

// //启动服务器
// $serv->start(); 


/******************************
   Web server
****************************/

/*$http = new swoole_http_server("0.0.0.0", 9503);

$http->on('request', function ($request, $response) {
    //var_dump($request->get, $request->post);
    $response->header("Content-Type", "text/html; charset=utf-8");
    $str = json_encode($request->get);
    $str.= trim( $request->post );
    $str.= "<h1>Hello Swoole. #".rand(1000, 9999)."</h1>";

    $response->end( $str );
});

$http->start();*/


/******************************
   设置定时器
****************************/
#可以启用 nohup实现 守护进程： 

# file 里面定时器执行功能函数 
# php -f file >/dev/null 2>&1

/*//每隔2000ms触发一次  
swoole_timer_tick(2000, function ($timer_id) {
	@file_put_contents('/data/swoole_timer', 'tick-2000ms'.PHP_EOL, FILE_APPEND);
    //echo "tick-2000ms\n";  
});  
  
//3000ms后执行此函数  
swoole_timer_after(3000, function () {  
    @file_put_contents('/data/swoole_timer', 'after 3000ms.'.PHP_EOL, FILE_APPEND);
    //echo "after 3000ms.\n";
});  */

