<?php
header('Content-type:text/json');
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/22/16
 * Time: 10:36 AM
 */
require_once('services/IPQuery.php');
require_once('model/IPSequence.php');
$queryPara = json_decode($_POST['data']);
//查询方法
$queryMethod=$_GET['method'];
echo $queryMethod;
$querClient = new IPQuery();
$ipCount = $querClient->queryIPRankByName($queryPara->name, $queryPara->range->start, $queryPara->range->stop, $queryPara->withScores);
$res = new IPSequence();
$res->name = $queryPara->name;
$res->createTime = null;
$res->ipCount = $ipCount;
echo json_encode($res);
