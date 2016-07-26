<?php
header('Content-type:text/json');
require_once('model/IPSequence.php');
require_once('services/IPQuery.php');
$ipData = new IPSequence();
$ipData->set(json_decode($_POST['data']));
//if(isset($_POST['data'])){
//    echo 'is set';
//}else{
//    echo 'not set';
//}
$queryClient = new IPQuery();
try {
    $queryClient->addIPRank($ipData);
} catch (Exception $e) {
    echo json_encode(array('status' => 'error'));
}
$ret = array('status' => 'ok');
echo json_encode($ret);
?>
