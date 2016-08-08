<?php
header('Content-type:text/json');
require_once('model/IPRank.php');
require_once('services/queryRankService.php');

function dumpToWeb(array $arr)
{
    echo 'array:[<br>';
    foreach ($arr as $key => $val) {
        echo $key . '=>' . $val . '<br>';
    }
    echo ']';
}

$rankData = new IPRank();

if (isset($_POST['name'])
    && isset($_POST['create_time'])
    && isset($_POST['table'])
) {
    $_POST['table'] = preg_replace('/[\x00-\x1F]/', '', $_POST['table']);
    $rank = json_decode($_POST['table']);
    if ($rank == null) {
        echo "{status' :'data format error}" . "\n";
        echo "POST string:\n<br/>";
        dumpToWeb($_POST);
        return;
    }
    $data = array('name' => $_POST['name'], 'time' => $_POST['create_time'], 'rank' => (array)$rank);
} else if (isset($_GET['name'])
    && isset($_GET['create_time'])
    && isset($_GET['table'])
) {
    $_GET['table'] = preg_replace('/[\x00-\x1F]/', '', $_GET['table']);
    $rank = json_decode($_GET['table']);
    if ($rank == null) {
        echo "{status' :'data format error}" . "\n";
        echo "GET string:\n<br/>";
        dumpToWeb($_GET);
        return;
    }
    $data = array('name' => $_GET['name'], 'time' => $_GET['create_time'], 'rank' => (array)$rank);
} else if (isset($_POST['data'])) {
    $_POST['data'] = preg_replace('/[\x00-\x1F]/', '', $_POST['data']);
    $data = json_decode($_POST['data']);
} else {
    echo "{status' :'data format error}" . "\n";
    return;
}

$rankData->set($data);
$queryClient = new rankQuery();
try {
    $ok = $queryClient->addRank($rankData);
    $ret = array('status' => $ok);
} catch (Exception $e) {
    $ret = array('status' => 'Exception: ' . $e->getMessage());
}
echo json_encode($ret);
?>
