<?php

/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/21/16
 * Time: 7:50 PM
 */
require_once('connectionConfig.php');

//keys,discarded
define('LastestUpdateKey', 'LASTEST_TIME_TABLE');
//define('TimeListKey', 'TIME_LIST');
define('NameSetKey', 'NAME_SET');
define('TMPZUnionKey', 'TMP_UNION_KEY');
define('TimestampKey', 'TIMESTAPM_SET');


//dbs
define('IPRankDB', 0);
define('InfoDB', 1);
define('TimeDB', 2);
define('UnionDB', 3);

//keys
define('nameHash', 'NAME_ID_TABLE');//name与id对应关系的哈希表key,该key在InfoDB中
define('nameCounter', 'NAME_COUNTER');//生成name对应id的计数器
define('logCounter', 'LOG_COUNTER');

define('LOG_PATH', '/Users/houchen/php.log');

class IPQuery
{
    private $redisConn = null;

    public function __construct()
    {
        if (!$this->redisConn) {
            $this->redisConn = new Redis();
            $this->redisConn->connect(redisConfig::$redisAddr, redisConfig::$redisPort);
        }
    }

    public function __destruct()
    {
        if ($this->redisConn != null) {
            $this->redisConn->close();
        }
    }

    public function addIPRank(IPSequence $ipRank)
    {
        //基本信息和id生成
        $this->redisConn->select(InfoDB);
        $name_exist = $this->redisConn->hGet(nameHash, $ipRank->name);
        if ($name_exist == false) {
            $nameID = $this->redisConn->incr(nameCounter);
            $this->redisConn->hSetNx(nameHash, $ipRank->name, $nameID);
        } else {
            $nameID = $name_exist;
        }

        $logID = $this->redisConn->incr(logCounter);

        //校验时间是不是存在
        //保存时间点信息
        $this->redisConn->select(TimeDB);

        $this->redisConn->zAdd($nameID, $ipRank->createTime, $logID);
        //保存该时间信息的排名信息
        $rank = $ipRank->Rank;
        foreach ($rank as $ip => $score) {
            $IPlong=ip2long($ip);
            $this->redisConn->select(IPRankDB);
            $this->redisConn->zAdd($logID, $score, $ip);
//            $this->redisConn->zAdd($logID, $IPlong, $score);
            $this->redisConn->select(UnionDB);
            $this->redisConn->zIncrBy($nameID,$score,$ip);
//            $this->redisConn->zAdd($nameID,$IPlong,$score);
        }

        //在此union?
    }

    public function queryRankByName($name, $start = 0, $stop = -1, $withScores = false)
    {
        //查name对应的id,拿到该id,去查对应的所有logid
        $this->redisConn->select(InfoDB);
        $nameID = $this->redisConn->hGet(nameHash, $name);
        if ($nameID == false) {
            return null;
        }

        $this->redisConn->select(TimeDB);
        //防止过大,zCount计数后,分批union,如果有按name筛选,则这里就不需要union
        $logIDs = $this->redisConn->zRange($nameID, 0, -1);
        error_log(print_r($logIDs, true), 3, LOG_PATH);
//        $this->redisConn->select(IPRankDB);
        $this->redisConn->select(UnionDB);
//        $logIDs['union'] = TMPZUnionKey;
        //去查询最近可用的已union的表

//        $this->redisConn->zUnion(TMPZUnionKey, $logIDs);

//        $ranks = $this->redisConn->zRevRange(TMPZUnionKey, $start, $stop, $withScores);
        $ranks=$this->redisConn->zRevRange($nameID,$start,$stop,$withScores);
        //instead delete
        $this->redisConn->move(TMPZUnionKey, UnionDB);
        $this->redisConn->del(TMPZUnionKey);
        $this->redisConn->select(UnionDB);
        $cur_time = time();
        error_log(print_r('curr_time:' . $cur_time, true), 3, LOG_PATH);
        if($this->redisConn->renameNx(TMPZUnionKey, $nameID . $cur_time)==false){
            //rename error
        }
        return $ranks;
    }

    // name redis匹配
    public function queryRankByTimeInterval($name, $startTime, $stopTime, $withScore = false)
    {
        $this->redisConn->select(InfoDB);
        $name_id = $this->redisConn->hGet(nameHash, $name);
        if ($name_id == false) {
            return 0;
        }

        $this->redisConn->select(TimeDB);
        $logIDs = $this->redisConn->zRangeByScore($name_id, $startTime, $stopTime);

        $this->redisConn->select(IPRankDB);
        $this->redisConn->zUnion(TMPZUnionKey, $logIDs);
        $ranks = $this->redisConn->zRevRange(TMPZUnionKey, 0, -1, $withScore);
        //move Tmp union to unionDB
        $this->redisConn->move(TMPZUnionKey, UnionDB);
        $this->redisConn->del(TMPZUnionKey);
        $this->redisConn->select(UnionDB);
        $cur_time = time();
        error_log(print_r('curr_time:' . $cur_time, true), 3, LOG_PATH);
        $this->redisConn->renameKey(TMPZUnionKey, $name_id . $cur_time);
        return $ranks;
    }

    public function queryIPByCIDR()
    {

    }

    public function doQuery($NamePattern, $StartTime, $EndTime, $IP, $IPMask, $count)
    {

    }

    public function deleteRankByName($name, $withTime = false)
    {
        $this->redisConn->select(InfoDB);
        $nameID = $this->redisConn->hGet(nameHash, $name);
        if ($nameID == false) {
            return 0;
        } else {
            $this->redisConn->hDel(nameHash, $nameID);
        }

        $this->redisConn->select(TimeDB);
        //
        $log_IDs = $this->redisConn->zRange($nameID, 0, -1);
        $this->redisConn->del($nameID);
        $this->redisConn->select(IPRankDB);
        $deleted = $this->redisConn->del($log_IDs);
        $this->redisConn->select(UnionDB);
        $this->redisConn->del($nameID);
        //检查删除数量
        if ($deleted == false) {
            $deleted = 0;
        }
        return $deleted;
    }

}