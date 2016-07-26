<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 2:59 PM
 */

header('Content-type:text/json');

require_once('services/IPQuery.php');
require_once('model/IPSequence.php');

$queryPara = json_decode($_POST['data']);
if (isset($_GET['method'])) {
    $queryMethod = $_GET['method'];
} else {
    $queryMethod = 'queryIPRankByName';
}
$query = new IPQuery();
$ipRank = null;
switch ($queryMethod) {
    case 'queryIPRankByName':
        $ipRank = $query->queryRankByName($queryPara->name,
            $queryPara->range->start,
            $queryPara->range->stop,
            $queryPara->withScores);
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
$res = new IPSequence();
$res->name = $queryPara->name;
$res->createTime = null;
$res->Rank = $ipRank;
echo json_encode($res);