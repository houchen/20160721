<?php
/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/25/16
 * Time: 1:59 PM
 */

require_once('services/queryRankService.php');
if (isset($_POST['data'])) {
    $delPara = json_decode($_POST['data']);
    $client = new rankQuery();
    $count = $client->deleteByName($delPara->name);
} else if (isset($_GET['name'])) {
    $client = new rankQuery();
    $count = $client->deleteByName($_GET['name']);
} else {
    echo json_encode(array('msg' => 'delete error'));
    return;
}
$ret = array('count' => $count);
echo json_encode($ret);
