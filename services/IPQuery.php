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
define('TimeListKey', 'TIME_LIST');
define('NameSetKey', 'NAME_SET');
define('TMPZUnionKey', 'TMP_UNION_KEY');
define('TimestampKey', 'TIMESTAPM_SET');


//dbs
define('IPRankDB', 0);
define('InfoDB', 1);
define('TimeDB', 2);

//keys
define('nameCounter', 'NAME_COUNTER');//生成name对应id的计数器
define('nameHash','NAME_ID_TABLE');//name与id对应关系的哈希表key,该key在InfoDB中
define('logCounter','LOG_COUNTER');

class IPQuery
{
    private $redisConn = null;
    //已经去掉这个变量了，返回的时候自己创建合适的变量
    private $result = null;

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

    public function addIPSequence(IPSequence $ipRank)
    {
//        $this->redisConn->select(IPRankDB);
//        foreach ($ipRank->ipCount as $ip => $count) {
//            $this->redisConn->zIncrBy($ipRank->name . $ipRank->createTime, $count, $ip);
//            $this->redisConn->get()
//        }
////        $this->redisConn->hSet(LastestUpdateKey, $ipSeq->name, $ipSeq->createTime);
//        //改name对应的所有时间点
//        $this->redisConn->select(TimeListDB);
//        $this->redisConn->lPush($ipRank->name, $ipRank->createTime);
////        $this->redisConn->sAdd(NameSetKey, $ipSeq->name);
////        $this->redisConn->sAdd(TimestampKey, $ipSeq->createTime);

        //基本信息和id生成
        $this->redisConn->select(InfoDB);
        $nameID=$this->redisConn->incr(nameCounter);
        $logID=$this->redisConn->incr(logCounter);
        $this->redisConn->hset(nameHash,$ipRank->name,$nameID);

        //保存时间点信息
        $this->redisConn->select(TimeDB);
        $this->redisConn->zAdd($nameID,$ipRank->createTime,$logID);

        //保存该时间信息的排名信息
        $this->redisConn->select(IPRankDB);
        $rank=$ipRank->Rank;
        foreach ($rank as $ip=>$score) {
            $this->redisConn->zAdd($logID,$score,$ip);
        }
    }

    public function queryIPRankByName($ipName, $start = 0, $stop = -1, $withScores = false)
    {
        $this->redisConn->select(IPRankDB);
        $this->result = $this->redisConn->zRevRange($ipName, $start, $stop, $withScores);
        return $this->result;
    }
    //通过createTime拿到对应的logID
    public function querylogIDByTimeInterval($nameID,$startTime,$endTime){
        $this->redisConn->select(TimeDB);
        //查询nameID时间段范围内的所有logID
        $res = $this->redisConn->zRangeByScore($nameID,$startTime,$endTime);
       return $res;
    }

    public function queryIPByTimestamp()
    {

    }

    // name redis匹配
    public function queryIPRankByTimeInterval()
    {
        $this->redisConn->select(IPRankDB);
        $names = $this->redisConn->sScan(NameSetKey, 0);
        foreach ($names as $name) {
            $this->redisConn->zUnion();
        }


//        $this->redisConn->zUnion(TMPUnionKey,)

    }

    public function queryIPByCIDR()
    {

    }

    public function doQuery($NameMath, $StartTime, $EndTime, $IP, $IPMask)
    {

    }
}
