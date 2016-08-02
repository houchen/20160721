<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/22/16
 * Time: 10:36 AM
 */
header('Content-type:text/json');

require_once('services/queryRankService.php');
require_once('model/IPRank.php');
if (isset($_GET['method'])) {
    $queryMethod = $_GET['method'];
} else {
    $queryMethod = 'queryIPRankByName';
}
if (isset($_POST['data'])) {
    $queryPara = json_decode($_POST['data']);
} else {
    $queryPara = null;
}
$query = new rankQuery();
$Rank = null;
switch ($queryMethod) {
    case 'queryRankByName':
        $Rank = $query->queryRankByName2($queryPara->name,
            $queryPara->start,
            $queryPara->stop,
            $queryPara->withScores,
            $queryPara->withTime,
            $queryPara->byScore);
        $json = new IPRank();
        $json->rank = $Rank;
        $json->name = $queryPara->name;
        $json->time = time();
        echo json_encode($json);
        break;
    case 'queryRankByNamePattern':
        $ranks = $query->queryRankByNamePattern($queryPara->name,
            $queryPara->start,
            $queryPara->stop,
            $queryPara->withScores,
            $queryPara->withTime,
            $queryPara->byScore);
        echo json_encode($ranks);
        break;
    case 'queryRankByTimeInterval':
        $Rank = $query->queryRankByTimeInterval2($queryPara->name,
            $queryPara->start,
            $queryPara->stop,
            $queryPara->withScores,
            $queryPara->withTime,
            $queryPara->count);
        echo json_encode($Rank);
        break;
    case 'getNameList':
        $nameList = $query->getNameList();
        echo json_encode($nameList);
        return;
    case 'queryTimeRange':
        $timeRange = $query->queryTimeRangeByName($queryPara->name);
        echo json_encode($timeRange);
        return;
    case '':
        echo json_encode(array('msg' => 'query API does\'t exist'));
        break;
}
?>
