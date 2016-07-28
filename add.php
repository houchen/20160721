<?php
header('Content-type:text/json');
require_once('model/IPRank.php');
require_once('services/queryRankService.php');
$ipData = new IPRank();
$ipData->set(json_decode($_POST['data']));
$queryClient = new rankQuery();
try {
    $queryClient->addRank($ipData);
} catch (Exception $e) {
    echo json_encode(array('status' => 'error'));
}
$ret = array('status' => 'ok');
echo json_encode($ret);
?>
