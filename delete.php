<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 1:59 PM
 */

require_once('services/queryRankService.php');
$client = new rankQuery();
if (isset($_POST['data'])) {
    $delPara = json_decode($_POST['data']);
    if (isset($delPara->start) && isset($delPara->stop)) {
        $count = $client->deleteTimeIntervalByName($delPara->name, $delPara->start, $delPara->stop);
    } else {
        $count = $client->deleteByName($delPara->name);
    }
} else if (isset($_GET['name'])) {
    $count = $client->deleteByName($_GET['name']);
} else {
    echo json_encode(array('msg' => 'delete error'));
    return;
}
$ret = array('count' => $count);
echo json_encode($ret);
