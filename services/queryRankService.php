<?php

/**
 * Created by PhpStorm.
 * User: houchen
 * Date: 7/21/16
 * Time: 7:50 PM
 */
require_once('redisConfig.php');
require_once('model/status.php');

//keys,discarded
//define('LastestUpdateKey', 'LASTEST_TIME_TABLE');
define('NameSetKey', 'NAME_SET');
define('TMPZUnionKey', 'TMP_UNION_KEY');
define('TimestampKey', 'TIMESTAPM_SET');


//dbs
define('RankDB', 0);
define('InfoDB', 1);
define('TimeDB', 2);
define('UnionDB', 3);
define('LatestUpdateDB', 4);

//keys
define('nameIDKey', 'NAME_ID_TABLE');//name与id对应关系的哈希表key,该key在InfoDB中
define('nameCounter', 'NAME_COUNTER');//生成name对应id的计数器
define('logCounter', 'LOG_COUNTER');

define('LOG_PATH', '/Users/houchen/php.log');

class rankQuery
{
    private $_redisConn = null;

    public function __construct()
    {
//        if (!$this->_redisConn) {
//            if (isset($_COOKIE['redisConn'])) {
//                $this->_redisConn = $_COOKIE['redisConn'];
//            } else {
//                $this->_redisConn = new Redis();
//                $this->_redisConn->connect(redisAddr, redisPort);
//                setcookie('redisConn',$this->_redisConn,time()+86400,'/');
//            }
//        }
        if ($this->_redisConn == null) {
            $this->_redisConn = new Redis();
            $this->_redisConn->connect(redisAddr, redisPort);
        }
    }

    public function __destruct()
    {
        if ($this->_redisConn != null) {
            $this->_redisConn->close();
        }
    }

    private function use ($db)
    {
        $this->_redisConn->select($db);
    }

    private function getNameID($name)
    {
        $this->use(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return null;
        } else {
            return $nameID;
        }
    }

    private function addName($name)
    {

    }

    private function checkTime($rank)
    {
        $rankTime = new ReflectionObject($rank);
        if ($rankTime->hasMethod('time')) {
            return $rank->time();
        } else {
            return time();
        }
    }

    private function associateRankTime($nameID, &$ranks, $withScore)
    {
        $ranksAndTime = array();
        $this->use(LatestUpdateDB);
        if ($withScore) {
            $timeList = $this->_redisConn->hMGet($nameID, array_keys($ranks));
            foreach ($ranks as $key => $score) {
                $ranksAndTime[$key] = array($score, $timeList[$key]);
            }
        } else {
            $timeList = $this->_redisConn->hMGet($nameID, $ranks);
            foreach ($ranks as $key) {
                $ranksAndTime[$key] = $timeList[$key];
            }
        }
        return $ranksAndTime;
    }

    public function addRank(IPRank $rank)
    {
        //查找名字的ID,或者添加新的名字
        $this->use(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $rank->name);
        if ($nameID == false) {
            $nameID = $this->_redisConn->incr(nameCounter);
            $this->_redisConn->hSet(nameIDKey, $rank->name, $nameID);
        }

        //计算排名更新的时间
        $logID = $this->_redisConn->incr(logCounter);
        $time = $this->checkTime($rank);

        //添加时间序列
        $this->use(TimeDB);
        $this->_redisConn->zAdd($nameID, $time, $logID);

        //更新总时段排名
        $this->use(UnionDB);
        foreach ($rank->rank as $key => $score) {
            $this->_redisConn->zIncrBy($nameID, $score, $key);
        }

        //更新分时段排名
        $this->use(RankDB);
        foreach ($rank->rank as $key => $score) {
            $this->_redisConn->zIncrBy($logID, $score, $key);
        }

        $this->use(LatestUpdateDB);
        foreach ($rank->rank as $key => $score) {
            $lastTime = $this->_redisConn->hGet($nameID, $key);
            if ($lastTime == false || $lastTime < $time) {
                $this->_redisConn->hSet($nameID, $key, $time);
            }
        }
        return status::OK;
    }

    public function addIPRank(IPRank $ipRank)
    {
        //基本信息和id生成
        $this->_redisConn->select(InfoDB);
        $name_exist = $this->_redisConn->hGet(nameIDKey, $ipRank->name);
        if ($name_exist == false) {
            $nameID = $this->_redisConn->incr(nameCounter);
            $this->_redisConn->hSetNx(nameIDKey, $ipRank->name, $nameID);
            $this->_redisConn->hSet(LatestUpdateDB, $ipRank->name, $ipRank->createTime);
        } else {
            $nameID = $name_exist;
//            $time=$this->redisConn->hGet(latestUpdateKey,$ipRank->name);
//            if($time!=false&& $time<$ipRank->createTime){
//                $this->redisConn->hSet(latestUpdateKey,$ipRank->name,$ipRank->createTime);
//            }else{
//                //error,存在id,但是不存在最后一次更新时间
//            }
        }

        $logID = $this->_redisConn->incr(logCounter);

        //校验时间是不是存在
        //保存时间点信息
        $this->_redisConn->select(TimeDB);
        $this->_redisConn->zAdd($nameID, $ipRank->createTime, $logID);
        //保存该时间信息的排名信息
        $rank = $ipRank->Rank;
        $this->_redisConn->select(RankDB);
        foreach ($rank as $ip => $score) {
//            $IPlong = ip2long($ip);
            $this->_redisConn->zIncrBy($logID, $score, $ip);
        }
        $this->_redisConn->select(UnionDB);
        foreach ($rank as $ip => $score) {
            $this->_redisConn->zIncrBy($nameID, $score, $ip);
        }
        //在此union?

        $this->_redisConn->select(LatestUpdateDB);
        foreach ($rank as $ip => $score) {
            $lastTime = $this->_redisConn->hGet($nameID, $ip);
            if ($lastTime == false || $lastTime < $ipRank->createTime) {
                $this->_redisConn->hSet($nameID, $ip, $ipRank->createTime);
            }
        }
    }

    public function queryRankByName2($name, $start = 0, $stop = -1, $withScores = false, $withTime = false, $byScore = false)
    {
        $this->use(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) return null;//not exist

        $this->use(UnionDB);
        if ($byScore) {
            $ranks = $this->_redisConn->zRevRangeByScore($name, $start, $stop, array('withscore' => $withScores));
        } else {
            $ranks = $this->_redisConn->zRevRange($nameID, $start, $stop, $withScores);
        }
        if ($withTime == false) {
            return $ranks;
        } else {
            return $this->associateRankTime($nameID, $ranks, $withScores);
        }
    }

    public function queryRankByName($name, $start = 0, $stop = -1, $withScores = false, $withTime = false, $byScore = false)
    {
        //查name对应的id,拿到该id,去查对应的所有logid
        $this->_redisConn->select(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return null;
        }

        $this->_redisConn->select(TimeDB);
        //防止过大,zCount计数后,分批union,如果有按name筛选,则这里就不需要union
        $logIDs = $this->_redisConn->zRange($nameID, 0, -1, $withTime);
        error_log(print_r($logIDs, true), 3, LOG_PATH);
//        $this->redisConn->select(IPRankDB);
        $this->_redisConn->select(UnionDB);
//        $logIDs['union'] = TMPZUnionKey;
        //去查询最近可用的已union的表

//        $this->redisConn->zUnion(TMPZUnionKey, $logIDs);
//        $ranks = $this->redisConn->zRevRange(TMPZUnionKey, $start, $stop, $withScores);

        if ($byScore) {
            $ranks = $this->_redisConn->zRevRangeByScore($name, $start, $stop, $withScores);
        } else {
            $ranks = $this->_redisConn->zRevRange($nameID, $start, $stop, $withScores);
        }
        if ($ranks == false) {
            return null;
        }

        if ($withTime) {
            $json_array = array();
            if ($withScores) {
                $this->_redisConn->select(LatestUpdateDB);
                $timeList = $this->_redisConn->hMGet($nameID, array_keys($ranks));
                if ($timeList == false) {
                    throw new Exception('get time list error');
                }
                foreach ($ranks as $ip => $score) {
                    $json_array[$ip] = array($score, $timeList[$ip]);
                }
            } else {
                $this->_redisConn->select(LatestUpdateDB);
                $timeList = $this->_redisConn->hMGet($nameID, $ranks);
                if ($timeList == false) {
                    throw new Exception('get time list error');
                }
                foreach ($ranks as $ip) {
                    $json_array[$ip] = array($timeList[$ip]);
                }
            }
            return $json_array;
        }
        return $ranks;
    }

    public function queryRankByTimeInterval2($name, $startTimestamp, $stopTimestamp, $withScores = false, $withTime = false, $count = 10)
    {
        $this->use(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false || $stopTimestamp < $startTimestamp) {
            return null;
        }

        $this->use(TimeDB);
        $logIDs = $this->_redisConn->zRangeByScore($nameID, $startTimestamp, $stopTimestamp);

        $this->use(RankDB);
        $mergedKey = $nameID . '-' . strval($startTimestamp) . '-' . strval($stopTimestamp);
        $this->_redisConn->zUnion($mergedKey, $logIDs);
        $this->_redisConn->move($mergedKey, UnionDB);

        $this->use(UnionDB);
        $ranks = $this->_redisConn->zRevRange($mergedKey, 0, $count, $withScores);

        $this->_redisConn->del($mergedKey);

        if ($withTime == true) {
            return $this->associateRankTime($nameID, $ranks, $withScores);
        } else {
            return $ranks;
        }

    }

    // name redis匹配
    public function queryRankByTimeInterval($name, $startTime, $stopTime, $withScore = false)
    {
        $this->_redisConn->select(InfoDB);
        $name_id = $this->_redisConn->hGet(nameIDKey, $name);
        if ($name_id == false) {
            return 0;
        }

        $this->_redisConn->select(TimeDB);
        $logIDs = $this->_redisConn->zRangeByScore($name_id, $startTime, $stopTime);

        $this->_redisConn->select(RankDB);
        $this->_redisConn->zUnion(TMPZUnionKey, $logIDs);
        $ranks = $this->_redisConn->zRevRange(TMPZUnionKey, 0, -1, $withScore);
        //todo:缓存union结果
        $this->_redisConn->move(TMPZUnionKey, UnionDB);
        $this->_redisConn->del(TMPZUnionKey);
        $this->_redisConn->select(UnionDB);
        $cur_time = time();
        error_log(print_r('curr_time:' . $cur_time, true), 3, LOG_PATH);
//        $this->redisConn->renameKey(TMPZUnionKey, $name_id . $cur_time);
        $this->_redisConn->del(TMPZUnionKey);
        return $ranks;
    }

    public function queryIPByCIDR()
    {

    }

    public function doQuery($NamePattern, $StartTime, $EndTime, $IP, $IPMask, $count)
    {

    }


    public function deleteByName($name, $startTime = 0, $stopTime = -1, $byRank = false)
    {
        $this->use(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return 0;
        } else {
            $this->_redisConn->hDel(nameIDKey, $name);
        }

        $this->use(TimeDB);
        $logIDs = $this->_redisConn->zRange($nameID, 0, -1);
        $this->_redisConn->del($nameID);
        $this->use(RankDB);
        $deleteCount = $this->_redisConn->del($logIDs);
        $this->use(UnionDB);
        $this->_redisConn->del($nameID);
        $this->use(LatestUpdateDB);
        $this->_redisConn->del($nameID);
        return $deleteCount;
    }

    public function deleteRankByName($name, $withTime = false)
    {
        $this->_redisConn->select(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return 0;
        } else {
            $this->_redisConn->hDel(nameIDKey, $nameID);
        }

        $this->_redisConn->select(TimeDB);
        //
        $log_IDs = $this->_redisConn->zRange($nameID, 0, -1);
        $this->_redisConn->del($nameID);
        $this->_redisConn->select(RankDB);
        $deleted = $this->_redisConn->del($log_IDs);
        $this->_redisConn->select(UnionDB);
        $this->_redisConn->del($nameID);
        $this->_redisConn->select(LatestUpdateDB);
        $this->_redisConn->del($nameID);
        //检查删除数量
        if ($deleted == false) {
            $deleted = 0;
        }
        return $deleted;
    }

    public function getNameList($namePattern = '*', $count = 100)
    {
        $this->use(InfoDB);

        session_start();
        if (isset($_SESSION['nameScanIterator'])) {
            $it = $_SESSION['nameScanIterator'];
        } else {
            $it = null;
        }
        $names = $this->_redisConn->hScan(nameIDKey, $it, $namePattern, $count);
        if ($it != 0) {
            //保存游标
            $_SESSION['nameScanIterator'] = $it;
        } else {
            //一次迭代扫描已经完成,删除游标
            unset($_SESSION['nameScanIterator']);
        }
        if ($names == false) {
            $names = null;
        }
        return array_keys($names);
    }

    public function queryTimeRangeByName($name)
    {
        $this->_redisConn->select(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        $this->_redisConn->select(TimeDB);
        $start = $this->_redisConn->zRange($nameID, 0, 0)[0];
        $end = $this->_redisConn->zRange($nameID, -1, -1)[0];
        return array('start' => $start, 'end' => $end);
    }
}