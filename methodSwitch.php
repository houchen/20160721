<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 2:59 PM
 */

header('Content-type:text/json');

require_once('services/queryRankService.php');
require_once('model/IPRank.php');

//session_destroy();
$queryPara = json_decode($_POST['data']);
if (isset($_GET['method'])) {
    $queryMethod = $_GET['method'];
} else {
    $queryMethod = 'queryIPRankByName';
}
$query = new rankQuery();
$ipRank = null;
switch ($queryMethod) {
    case 'queryIPRankByName':
        $ipRank = $query->queryRankByName2($queryPara->name,
            $queryPara->range->start,
            $queryPara->range->stop,
            $queryPara->withScores,
            $queryPara->withTime);
        break;
    case 'queryIPRankByTimeInterval':
        $ipRank = $query->queryRankByTimeInterval($queryPara->name,
            $queryPara->range->start,
            $queryPara->range->stop,
            $queryPara->withScores);
        break;
    case 'getNameList':
        $nameList = $query->getNameList($queryPara->name, $queryPara->count);
        echo json_encode($nameList);
        return;
    case 'queryTimeRange':
        $timeRange = $query->queryTimeRangeByName($_GET['name']);
        echo json_encode($timeRange);
        return;
    case '':
        break;
}
$res = new IPRank();
$res->name = $queryPara->name;
$res->createTime = null;
$res->Rank = $ipRank;
echo json_encode($res);