<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 1:59 PM
 */

require_once ('services/IPQuery.php');
$delPara=json_decode($_POST['data']);
$client=new IPQuery();
$count=$client->deleteIPByName($delPara->name);
$ret=array('name'=>$delPara->name,'count'=>$count);
echo json_encode($ret);
