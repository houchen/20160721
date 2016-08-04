<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 4:44 PM
 * redis union命令的速度测试,mac机器上,union两个300W的有序集合,需要大约9s
 */

class runtime
{
    var $StartTime = 0;
    var $StopTime = 0;

    function get_microtime()
    {
        list($usec, $sec) = explode(' ', microtime());
        return ((float)$usec + (float)$sec);
    }

    function start()
    {
        $this->StartTime = $this->get_microtime();
    }

    function stop()
    {
        $this->StopTime = $this->get_microtime();
    }

    function spent()
    {
        return round(($this->StopTime - $this->StartTime) * 1000, 1);
    }

}

//test union performance
function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
//$clock=new runtime();
$redis = new Redis();
$redis->connect('localhost');
//for ($i = 0; $i < 2000000; $i++) {
//    $randstr=generateRandomString(15);
//    $redis->zAdd('key1', $i,$randstr);
//    $redis->zAdd('key2',$i+rand(-10,10),$randstr);
//}
//
//$clock->start();
//$redis->zUnion('key2',array('key1','key2'));
//$clock->stop();
//echo $clock->spent().' ms';
//
//$redis->del('key1');
for($i=0;$i<10000;$i++){
    $randstr=generateRandomString(15);
    $redis->zAdd('key1w',$i,$randstr);
}
//
//$start=microtime();
//$redis->zUnion('key2',array('key1','key2'));
//$finish=microtime();
//echo $start.' '.$finish;



