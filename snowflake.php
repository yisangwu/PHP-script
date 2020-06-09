<?php

/**
 *  雪花片算法
 *  # ab -c 100 -n 100000 http://192.168.50.83/snow.php
 *
 **/

$dbhost = '192.168.50.83';
$dbuser = 'root';
$dbpw = 'pwd@1256';
$dbname = 'snowwake';
$dbport = 3306;

$con = mysqli_connect($dbhost, $dbuser, $dbpw, $dbname, $dbport);

if(!$con){
    die(sprintf('mysqli_connect() error', mysqli_connect_error()));
}

mysqli_query($con , "set names utf8");


define('START_TIME', 15916784638137);


/**
 * 41bit时间戳
 * @return bin
 */
function bin_timestamp()
{
    $bin_time = decbin(microtime(true)*10000- START_TIME) ;

    if(strlen($bin_time)>41){
        $bin_time = substr($bin_time, 0, 41);
    }else{
        $bin_time = str_pad($bin_time, 41, '0');
    }
    return (string)$bin_time;
}



/**
 * 10 机器ID
 * @return bin
 */
function bin_machine()
{
    $bin_machine = decbin(83);
    if(strlen($bin_machine) < 10){
        $bin_machine = str_pad($bin_machine, 10, '0');
    }else{
        $bin_machine = substr($bin_machine, 0, 10);
    }
    return $bin_machine;
}



$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$bin_sequence = decbin($redis->incr('xx'));

if(strlen($bin_sequence) < 12){
    $bin_sequence = str_pad($bin_sequence, 12, '0');
}else{
    $bin_sequence = substr($bin_sequence, 0, 12);
}

// 41bit时间戳 + 10 bit机器服务标识 + 12 bit序列号
$order_id = bindec(sprintf('0%s%s%s', bin_timestamp(), bin_machine(), $bin_sequence));
$error_id = sprintf('0 - %s  - %s  - %s', bin_timestamp(), bin_machine(), $bin_sequence);

if(!mysqli_query($con, "INSERT INTO snow_order SET order_id={$order_id}")){
    file_put_contents('/tmp/snow_error', sprintf('error：%s! error_id is:%s', mysqli_error($con), $error_id).PHP_EOL, FILE_APPEND);
}else{
    file_put_contents('/tmp/snow_succ', sprintf('succ, mysqli_insert_id: %s', mysqli_insert_id($con)).PHP_EOL, FILE_APPEND);
}


mysqli_close($con);

