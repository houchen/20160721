<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 8/15/16
 * Time: 2:31 PM
 */
$rd=new Redis();
$rd->connect('localhost');
$rd->zAdd('ztest',1,'a',2,'b');
$res=$rd->zRevRange('ztest',0,10,true);
var_dump($res);
