<?php

/**
 * User: houchen
 * Date: 7/21/16
 * Time: 7:50 PM
 */
require_once('config.php');
require_once('model/status.php');


//dbs
define('RankDB', 0);
define('InfoDB', 1);
define('TimeDB', 2);
define('UnionDB', 3);
define('LatestUpdateDB', 4);

//keys
define('nameIDKey', 'id:hash');//name与id对应关系的哈希表key,该key在InfoDB中
define('nameCounter', 'name:counter');//生成name对应id的计数器
define('logCounter', 'log:counter');
define('TMPZUnionKey', 'tmpUnion:set');


class rankQuery
{
    private $_redisConn = null;

    public function __construct()
    {
        //todo:检查cookie中是否有可用redis连接?
        //redis连接可以保存到cookie中吗?
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

    private function selectDB($db)
    {
        static $currDB = 0;
        if ($currDB != $db) {
            $this->_redisConn->select($db);
            $currDB = $db;
        }
    }

    private function getNameID($name)
    {
        $this->selectDB(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return null;
        } else {
            return $nameID;
        }
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
    //给每个name对应的rank加上最后一次更新时间
    //fixme:还没有完成,需不需要这个功能?
    private function associateRankTime($nameID, &$ranks, $withScore)
    {
        throw new Exception('not implement:associateRankTime');
        $ranksAndTime = array();
        $this->selectDB(LatestUpdateDB);
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
        $this->selectDB(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $rank->name);
        if ($nameID == false) {
            $nameID = $this->_redisConn->incr(nameCounter);
            $this->_redisConn->hSet(nameIDKey, $rank->name, $nameID);
        }

        //计算排名更新的时间
        $logID = $this->_redisConn->incr(logCounter);
        $time = $this->checkTime($rank);

        //添加时间序列
        $this->selectDB(TimeDB);
        $this->_redisConn->zAdd($nameID, $time, $logID);

        //更新总时段排名
        $this->selectDB(UnionDB);
        $scores = $rank->rank;
        if (is_array($scores)) {
            foreach ($scores as $key => $score) {
                $this->_redisConn->zIncrBy($nameID, $score, $key);
            }
        } else {
            throw  new Exception('illegal ranks');
        }

        //更新分时段排名
        $this->selectDB(RankDB);
        foreach ($scores as $key => $score) {
            //todo:
            $this->_redisConn->zIncrBy($logID, $score, $key);
        }


        //todo:保存最后一次更新时间?
//        $this->use(LatestUpdateDB);
//        foreach ($rank->rank as $key => $score) {
//            $lastTime = $this->_redisConn->hGet($nameID, $key);
//            if ($lastTime == false || $lastTime < $time) {
//                $this->_redisConn->hSet($nameID, $key, $time);
//            }
//        }
        return status::OK;
    }

    public function queryRankByNamePattern($namePattern = '*', $start = 0, $stop = -1, $withScores = false, $withTime = false, $byScore = false)
    {
        $nameIDs = $this->getNameIDList($namePattern, 100);
        if ($nameIDs == null) return null;
        $i = 0;
        $rankArr = null;
        foreach ($nameIDs as $name => $id) {
            $rank = $this->queryRankByID($id, $start, $stop, $withScores, $withTime, $byScore);
            $rankArr[$i++] = array('name' => $name, 'rank' => $rank);
        }
        return $rankArr;
    }

    public function queryRankByID($nameID, $start = 0, $stop = -1, $withScores = false, $withTime = false, $byScore = false)
    {
        $this->selectDB(UnionDB);
        if ($byScore) {
            $ranks = $this->_redisConn->zRevRangeByScore($nameID, $start, $stop, $withScores);
        } else {
            $ranks = $this->_redisConn->zRevRange($nameID, $start, $stop, $withScores);
        }
        if ($withTime == false) {
            return $ranks;
        } else {
            return $this->associateRankTime($nameID, $ranks, $withScores);
        }
    }

    public function queryRankByName2($name, $start = 0, $stop = -1, $withScores = false, $withTime = false, $byScore = false)
    {
        $this->selectDB(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) return null;//not exist
        return $this->queryRankByID($nameID, $start, $stop, $withScores, $withTime, $byScore);
    }


    public function queryTimeIntervalRankByID($nameID, $startTimestamp, $stopTimestamp, $withScores = false, $withTime = false, $count = 10)
    {


        $this->selectDB(TimeDB);
        //todo:按稍大的时间范围搜索可能已经存在的归并过的排序
        $logIDs = $this->_redisConn->zRangeByScore($nameID, $startTimestamp, $stopTimestamp, array('withscores' => false));

        if ($logIDs == false) return null;

        $mergedKey = $nameID . ':' . reset($logIDs) . ':' . end($logIDs);
        $this->selectDB(UnionDB);
        //已经有归并的key
        if (!$this->_redisConn->exists($mergedKey)) {
            $this->selectDB(RankDB);
            $this->_redisConn->zUnion($mergedKey, $logIDs);
            $this->_redisConn->move($mergedKey, UnionDB);
        }
        $this->selectDB(UnionDB);
        $this->_redisConn->expire($mergedKey, 900);//15分钟过期
        $ranks = $this->_redisConn->zRevRange($mergedKey, 0, $count, $withScores);
        if ($withTime == true) {
            $this->associateRankTime($nameID, $ranks, $withScores);
        }
        return $ranks;
    }

    public function queryRankByTimeInterval2($namePattern, $startTimestamp, $stopTimestamp, $withScores = false, $withTime = false, $count = 10)
    {
        //到2100年
        if ($startTimestamp < 1 || $stopTimestamp < 1 || $stopTimestamp > 4102419661) {
            return array('msg' => 'time error');
        }
        if ($namePattern == null) return array('msg' => 'name  error');
        $nameIDs = $this->getNameIDList($namePattern, 100);
        if ($nameIDs == null) return null;

        $i = 0;
        $rankArr = null;
        foreach ($nameIDs as $name => $id) {
            $rank = $this->queryTimeIntervalRankByID($id, $startTimestamp, $stopTimestamp, $withScores, $withTime, $count);
            $rankArr[$i++] = array('name' => $name, 'rank' => $rank);
        }
        return $rankArr;
    }

    public
    function doQuery($NamePattern, $StartTime, $EndTime, $IP, $IPMask, $count)
    {

    }

    public function deleteTimeIntervalByName($name, $startTime = 0, $stopTime = 0)
    {
        $nameID = $this->getNameID($name);
        if ($nameID == null) return 0;

        $this->selectDB(TimeDB);

        $logIDs = $this->_redisConn->zRangeByScore($nameID, $startTime, $stopTime, array('withscores' => false));
        if ($logIDs == false) return 0;

        $this->selectDB(RankDB);
        $this->_redisConn->zUnion(TMPZUnionKey, $logIDs);
        $this->_redisConn->move(TMPZUnionKey, UnionDB);
        $delCount = $this->_redisConn->del($logIDs);

        $this->selectDB(UnionDB);
        $this->_redisConn->zUnion($nameID, array($nameID, TMPZUnionKey), array(1, -1));
        $this->_redisConn->zRemRangeByScore($nameID, '-inf', 0);
        $this->_redisConn->del(TMPZUnionKey);

        $this->selectDB(InfoDB);
        $this->_redisConn->hDel(nameIDKey, $name);
        return $delCount;
    }

    public
    function deleteByName($name)
    {
        $this->selectDB(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        if ($nameID == false) {
            return 0;
        } else {
            $this->_redisConn->hDel(nameIDKey, $name);
        }

        $this->selectDB(TimeDB);
        $logIDs = $this->_redisConn->zRange($nameID, 0, -1);
        $this->_redisConn->del($nameID);
        $this->selectDB(RankDB);
        $deleteCount = $this->_redisConn->del($logIDs);
        $this->selectDB(UnionDB);
        $this->_redisConn->del($nameID);
//        $this->use(LatestUpdateDB);
//        $this->_redisConn->del($nameID);
        return $deleteCount;
    }

    public
    function deleteRankByName($name)
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

    public function getNameIDList($namePattern = '*', $count = 200)
    {
        $this->selectDB(InfoDB);
        //todo:在session中保存游标(不同次请求redis链接可能已经断开,改游标还可用吗?)
        $it = null;
        $names = $this->_redisConn->hScan(nameIDKey, $it, $namePattern, $count);
        if ($names == false) {
            $names = null;
        }
        return $names;
    }

    public
    function getNameList($namePattern = '*', $count = 200)
    {
        return array_keys($this->getNameIDList($namePattern, $count));
    }

    public
    function queryTimeRangeByName($name)
    {
        $this->_redisConn->select(InfoDB);
        $nameID = $this->_redisConn->hGet(nameIDKey, $name);
        $this->_redisConn->select(TimeDB);
        $start = $this->_redisConn->zRange($nameID, 0, 0)[0];
        $end = $this->_redisConn->zRange($nameID, -1, -1)[0];
        return array('start' => $start, 'end' => $end);
    }
}
