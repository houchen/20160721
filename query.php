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
    $queryMethod = 'queryRank';
}
$query = new rankQuery();
switch ($queryMethod) {
    case 'queryAllRank':
        $rk = $query->queryAllRank($_GET['count']);
        echo json_encode($rk);
        break;
    case 'queryRankByName':
        $rk = $query->queryNameAllRank($_GET['name'], $_GET['count']);
        echo json_encode($rk);
        break;
    case 'getNameList':
        $nameList = $query->fetchNameList();
        echo json_encode($nameList);
        break;
    case 'addWhiteMember':
        $count = $query->addWhite($_GET['member']);
        echo json_encode(array('count' => $count));
        break;
    case 'getWhiteList':
        $whiteList = $query->getWhiteList();
        echo json_encode($whiteList);
        break;
    case 'removeWhiteMember':
        $count = $query->removeWhiteList($_GET['member']);
        echo json_encode(array('count' => $count));
        break;
    case 'getPolicyList':
        $policy = $query->getPolicyList();
        echo json_encode($policy);
        break;
    case 'fetchDenyList':
        $denyList = $query->fetchDenyList($_GET['name']);
        echo json_encode($denyList);
        break;
    case 'fetchFormattedDenyList':
        $denyList = $query->fetchFormattedDenyList($_GET['service_name']);
        echo $denyList;
        break;
    case 'fetchFormattedWhiteList':
        $whiteList = $query->getFormattedWhiteList();
        echo $whiteList;
        break;
    case '':
        echo json_encode(array('msg' => 'API does\'t exist'));
        break;
}

