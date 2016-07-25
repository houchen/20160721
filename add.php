<?php
header('Content-type:text/json');
require_once('model/IPSequence.php');
require_once('services/IPQuery.php');
$ipData = new IPSequence();
$ipData->set(json_decode($_POST['data']));
$queryClient = new IPQuery();
try {
    $queryClient->addIPRank($ipData);
} catch (Exception $e) {
    echo json_encode(array('status' => 'error'));
}
$ret = array('status' => 'ok');
echo json_encode($ret);
?>
