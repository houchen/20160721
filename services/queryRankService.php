<?php

/**
 * User: houchen
 * Date: 7/21/16
 * Time: 7:50 PM
 */
require_once('config.php');
require_once('model/status.php');
date_default_timezone_set('Asia/Shanghai');
require_once('deps/logger/psr/Log/LoggerInterface.php');
require_once 'deps/logger/psr/Log/AbstractLogger.php';
require_once 'deps/logger/psr/Log/LogLevel.php';
require_once('deps/logger/klogger/Logger.php');


//keys
define('POLICY_CONF', 'policyConfig.json');
define('LOG_LEVEL', Psr\Log\LogLevel::DEBUG);

define('rankDB', 5);
define('LOG_PATH', './logs');

class rankQuery
{
    private $_redisConn = null;
    private $policy;
    private static $logger = null;

    public function __construct()
    {
        if ($this->_redisConn == null) {
            $this->_redisConn = new Redis();
            $this->_redisConn->connect(redisAddr, redisPort);
        }
        if (self::$logger == null) {
            self::$logger = new Katzgrau\KLogger\Logger(LOG_PATH, LOG_LEVEL);
        }
        $this->loadPolicy(POLICY_CONF);
    }

    public function __destruct()
    {
        if ($this->_redisConn != null) {
            $this->_redisConn->close();
        }
    }

    public function loadPolicy($policyFile)
    {
        $policyStr = file_get_contents($policyFile);
        $this->policy = json_decode($policyStr);
        if ($this->policy == null) {
            throw new Exception('decode policy config error');
        } else {
            return status::OK;
        }
    }

    private function selectDB($db)
    {
        static $currDB = -1;
        if ($currDB != $db) {
            $this->_redisConn->select($db);
            $currDB = $db;
        }
    }

    public function getPolicyList()
    {
        return $this->policy;
    }

    private function rankByTimeKey($name, $policy, $key)
    {
        return $name . ':' . $policy->time . ':rank:' . $key . ':zsort';
    }

    private function rankByScoreKey($name, $policy)
    {
        return $name . ':' . $policy->time . ':rank:zsort';
    }

    private function policyTimerKey($name, $policy)
    {
        return $name . ':' . $policy->time . ':reRankUpdate:timer';
    }

    private function whiteListKey()
    {
        return 'whiteList:set';
    }

    private function nameSetKey()
    {
        return 'allName:set';
    }

    private function denyListKey($name, $policy)
    {
        return $name . ':' . $policy->time . ':deny:set';
    }

    private function denyListUpdateTimer($name, $policy)
    {
        return $name . ':' . $policy->time . ':denyUpdate:timer';
    }

    private function touchLimitTimestampKey($name, $policy)
    {
        return $name . ':' . $policy->time . ':touchLimitTimestamp:hash';
    }

    private function checkTime($rank)
    {
        $rankTime = new ReflectionObject($rank);
        if ($rankTime->hasMethod('time')) {
            return $rank->time();
        } else if ($rankTime->hasProperty('time')) {
            return $rank->time;
        } else {
            return time();
        }
    }

    private function scoreSum($scoreArray)
    {
        $sum = 0;
        foreach ($scoreArray as $sa) {
            $j = json_decode($sa);
            $sum += $j->score;
        }
        return $sum;
    }

    //重新排名,并在必要的时候回收时间排名队列,删除相应的数据和纪录
    private function doReRank($name, $policy)
    {
        $this->selectDB(rankDB);
        $reRankKey = $this->rankByScoreKey($name, $policy);
        self::$logger->info('reRank key: ' . $reRankKey);
        $allSortedRank = $this->_redisConn->zRange($reRankKey, 0, -1);
        if (count($allSortedRank) <= 0) {
            self::$logger->warning('no members to reRank,key= ' . $reRankKey);
        } else if ($allSortedRank == false) {
            //todo:reRank键为空需要被删除,这里就会返回false
            return;
        }
        $trimTimeLimit = time() - $policy->time;
        //遍历每一个排名成员,给他们减分
        foreach ($allSortedRank as $member) {
            $minusKey = $this->rankByTimeKey($name, $policy, $member);
            //查找在policy->time之前时间的所有纪录,把它们按分数求和,然后把这些过期的纪录删除
            $decrementSumScore = $this->_redisConn->zRangeByScore($minusKey, '-inf', $trimTimeLimit);
            $decrSum = -1 * $this->scoreSum($decrementSumScore);
            if ($decrSum == 0) {
                self::$logger->debug('decrSum=0,reRank needless');
                continue;
            }
            //减去过期的元素
            $newVal = $this->_redisConn->zIncrBy($reRankKey, $decrSum, $member);
            self::$logger->debug('decrease to ' . $newVal .
                ', decrease total: ' . -1 * $decrSum .
                ', trim time limit: ' . $trimTimeLimit .
                '(' . date('Y-m-d H:i:s', $trimTimeLimit) . ')');
            $this->_redisConn->zRemRangeByScore($minusKey, '-inf', $trimTimeLimit);
            //主要做两步检查:1.分数为0的时候,检查对应的按时间排名的集合是否为空;2.反之,时间排名集合为空,分数是否为0
            //在分数被减到阀值以下时,对纪录超过阀值时间点的hash表进行更新,为下次超过阀值纪录做准备
            if ($newVal == 0) {
                $this->_redisConn->zRem($reRankKey, $member);
                self::$logger->info('member: ' . $member . ' removed from ' . $reRankKey);
                //当新值为0,则minusKey也应该没有元素
                if (($card = $this->_redisConn->zCard($minusKey)) == 0) {
                    $this->_redisConn->del($minusKey);
                    self::$logger->info('key: ' . $minusKey . ' deleted');
                    continue;
                } else {
                    //新排名分数已经为0,但是相应的时间排名列表还不为空
                    $this->_redisConn->del($minusKey);
                    self::$logger->error('rank is not consistent! minus key deleted members incorrect.' .
                        'reRankKey: ' . $reRankKey .
                        " ,member: " . $member .
                        " ,minus key: " . $minusKey .
                        " ,minusKey card:" . $card);
                }
            } else if ($newVal < 0) {
                //分值在正确的情况下不可能减成负的
                $this->_redisConn->zRem($reRankKey, $member);
                $this->_redisConn->del($minusKey);
                self::$logger->error("rank incorrect!decr to negative! reRankKey: " . $reRankKey .
                    " ,member: " . $member .
                    " ,minus key: " . $minusKey .
                    " ,newVal: " . $newVal);
            } else if ($newVal > 0
                && $newVal < $policy->score
                && $decrSum + $newVal >= $policy->score
            ) {
                //member 曾经超过阀值,并且这轮重新排名后分数减少到阀值以下,删除它达到阀值的时间点
                $ret = $this->_redisConn->hDel($this->touchLimitTimestampKey($name, $policy), $member);
                if ($ret == false || $ret == 0) {
                    self::$logger->error('member: ' . $member .
                        ' decreasingly less than score limit: ' . $policy->score .
                        '. But delete touch limit timestamp error. new score= ' . $newVal .
                        ' ,ret=' . $ret);
                } else {
                    self::$logger->info('member: ' . $member . ' touch limit timestamp has been removed from ' .
                        $this->touchLimitTimestampKey($name, $policy) . ', new score= ' . $newVal .
                        ', policy score: ' . $policy->score);
                }
            }
            if ($this->_redisConn->zCard($minusKey) == 0) {
                self::$logger->info('key : ' . $minusKey . ' has been deleted');
                //如果minusKey已经没有元素,则该member对应的分数应该为0
                if ($newVal == 0) {
                    //减去之后,排名集合中新的分数为0
                    $this->_redisConn->zRem($reRankKey, $member);
                    self::$logger->info('member: ' . $member . ' removed from: ' . $reRankKey);
                    continue;
                } else {
                    //对应的时间排序列表已经为空,但是分数还不为0
                    $this->_redisConn->zRem($reRankKey, $member);
                    self::$logger->error("sorted rank incorrect! reRankKey: " . $reRankKey .
                        " ,member: " . $member .
                        " ,minus key: " . $minusKey .
                        " ,score: " . $newVal);
                }
            }
        }
        //该name在策略下的排名都被删除完时,这是reRankKey已经不存在了。等待下一次查询排名的时候,在name集合中删除该name
    }

    private function reRankIfNecessary($name, $policy)
    {
        $timerKey = $this->policyTimerKey($name, $policy);
        if ($this->_redisConn->exists($timerKey)) {
            return;
        } else {
            $this->doReRank($name, $policy);
            $this->_redisConn->set($timerKey, '1');
//            $this->_redisConn->expire($timerKey, sqrt($policy->time));
            $this->_redisConn->expire($timerKey, 1);
        }
    }


    private function doAddRank($name, $time, $rankArr, $policy)
    {
        foreach ($rankArr as $member => $score) {
            //按time排名
            $rankKey = $this->rankByTimeKey($name, $policy, $member);
            //这里的value是分数喝时间的混合,因为一次过来的数据,可能有很多相同的分数
            $memberJSON = json_encode(array('score' => $score, 'member' => $member, 'time' => $time));
            if (($ret = $this->_redisConn->zIncrBy($rankKey, $time, $memberJSON)) != $time) {
                //在一个时间点上,有两个相同的ip/key分数
                self::$logger->error("add key at same time, key: " . $rankKey .
                    ' ,redis add return: ' . $ret .
                    ' ,member: ' . $memberJSON);
            }
        }
        $rankKey = $this->rankByScoreKey($name, $policy);
        foreach ($rankArr as $member => $score) {
            //按score排名
            if (($ret = $this->_redisConn->zIncrBy($rankKey, $score, $member)) == $score) {
                self::$logger->info("add new member in rank ,key: " . $rankKey .
                    " ,redis add return: " . $ret .
                    ' ,member: ' . $member);
            }
            //纪录score达到阀值的时间,只在第一次达到的时候才纪录(分数可能继续上涨)
            if ($ret >= $policy->score) {
                $this->_redisConn->hSetNx($this->touchLimitTimestampKey($name, $policy), $member, $time);
            }
        }
    }

    public function addRank(IPRank $rank)
    {
        $this->selectDB(rankDB);
        $time = $this->checkTime($rank);
        self::$logger->info('add rank,name: ' . $rank->name);
        foreach ($this->policy as $p) {
            $this->doAddRank($rank->name, $time, $rank->rank, $p);
            $this->reRankIfNecessary($rank->name, $p);
            $this->tryUpdateDenyList($rank->name, $p);
        }
        $this->_redisConn->sAdd($this->nameSetKey(), $rank->name);
        return status::OK;
    }


    public function tryUpdateDenyList($name, $policy)
    {
        $updateTimer = $this->denyListUpdateTimer($name, $policy);
        $doDenyListKey = $this->denyListKey($name, $policy);
        $this->selectDB(rankDB);
        //检查到没到需要更新的时间
        if ($this->_redisConn->exists($updateTimer)) {
            return;
        } else {
            $this->_redisConn->del($doDenyListKey);
            $this->_redisConn->set($updateTimer, '1');
//            $this->_redisConn->expire($updateTimer, sqrt($policy->time));
            $this->_redisConn->expire($updateTimer, 1);
        }
        self::$logger->debug('update deny list,name= ' . $name .
            ' ,policy->time= ' . $policy->time .
            ' ,policy->score= ' . $policy->score);
        $scoreRankKey = $this->rankByScoreKey($name, $policy);
        $deniedMember = $this->_redisConn->zRangeByScore($scoreRankKey, $policy->score, '+inf', array('withscores' => true));
        //这里的time不能从现在开始算
        $policyInfo = ':TTL:' . $policy->ttl . ':policy->time:' . $policy->time . ':policy->score:' . $policy->score;
        self::$logger->debug('denied member and score: ', $deniedMember);
        foreach ($deniedMember as $member => $score) {
            if ($this->_redisConn->sIsMember($this->whiteListKey(), $member)) {
                //在白名单里
                self::$logger->info('member: ' . $member . ' in white list,but score=' . $score);
            } else {
                self::$logger->debug('member: '.$member.' not in white list');
                //查找什么时候超过了分数阀值
                $expireAt = $this->_redisConn->hGet($this->touchLimitTimestampKey($name, $policy), $member);
                if ($expireAt == false) {
                    self::$logger->error('member: ' . $member . ' score= ' . $score .
                        ' touch score limit: ' . $policy->score .
                        '. But touch timestamp not found in ' . $this->touchLimitTimestampKey($name, $policy));
                    continue;
                }
                $expireAt = intval($expireAt) + $policy->ttl;
                $denyInfo = $expireAt . $policyInfo . ':score:' . $score;
                $this->_redisConn->hSet($doDenyListKey, $member, $denyInfo);
            }
        }
    }

    //查询一个name,在指定policy下的前count名
    public function queryRank($name, $policy, $count)
    {
        $this->reRankIfNecessary($name, $policy);
        $queryKey = $this->rankByScoreKey($name, $policy);
        $ranks = $this->_redisConn->zRevRange($queryKey, 0, $count, true);
        if ($ranks == false) {
            self::$logger->warning('query rank failed.key: ' . $queryKey);
            return null;
        }
        return $ranks;
    }

    //查询一个名字在所有策略下的前count名,在没有查到任何数据的情况下,回收name相关的key及纪录
    public function queryNameAllRank($name, $count)
    {
        $allRank = array();
        $i = 0;
        $nullCount = 0;
        foreach ($this->policy as $p) {
            $rk = $this->queryRank($name, $p, $count);
            if ($rk != null) {
                $allRank[$i++] = array('name' => $name . ':' . $p->time, 'rank' => $rk);
            } else {
                $nullCount++;
            }
        }
        if ($nullCount == count($this->policy)) {
            //本name所有策略都返回null,则说明,这个name已经没有任何数据
            //那么移除这个name,并检查相关的key是不是都一致
            $this->_redisConn->sRem($this->nameSetKey(), $name);
            foreach ($this->policy as $p) {
                $len = $this->_redisConn->hLen($this->touchLimitTimestampKey($name, $p));
                if ($len > 0) {
                    self::$logger->error('query rank is null in all policy, ' .
                        'but touch Limit hash len>0(' . $len . ')');
                    $this->_redisConn->del($this->touchLimitTimestampKey($name, $p));
                }
            }
        }
        return $allRank;
    }

    //查询所有排名的前count名
    public function queryAllRank($count)
    {
        $this->selectDB(rankDB);
        $ranks = array();
        $allName = $this->_redisConn->sMembers($this->nameSetKey());
        $i = 0;
        self::$logger->debug('all members: ', $allName);
        foreach ($allName as $name) {
            $rk = $this->queryNameAllRank($name, $count);
            foreach ($rk as $r) {
                $ranks[$i++] = $r;
            }
        }
        return $ranks;
    }

    public function fetchDenyList($name)
    {
        $this->selectDB(rankDB);
        $allPolicyDenyList = array();
        foreach ($this->policy as $p) {
            $this->reRankIfNecessary($name, $p);
            $denyListKey = $this->denyListKey($name, $p);
            $this->tryUpdateDenyList($name, $p);
            $denyList = $this->_redisConn->hGetAll($denyListKey);
            $allPolicyDenyList = $denyList + $allPolicyDenyList;
        }
        return $allPolicyDenyList;
    }

    public function fetchFormattedDenyList($name)
    {
        $rawDenyList = $this->fetchDenyList($name);
        $denyString = '';
        foreach ($rawDenyList as $member => $denyTime) {
            $denyTime = explode(':', $denyTime, 2);
            $denyString = $denyString . 'D ' . $member . ' ' . $denyTime[0] . "\r\n";
        }
        if ($denyString == '') {
            return 'D 256.256.256.256';
        }
        return $denyString;
    }

    public function addWhite($key)
    {
        $this->selectDB(rankDB);
        if ($this->_redisConn->sAdd($this->whiteListKey(), $key) == 1) {
            return 1;
        } else {
            return 0;
        }
    }

    public function removeWhiteList($key)
    {
        $this->selectDB(rankDB);
        if ($this->_redisConn->sRem($this->whiteListKey(), $key) == 1) {
            return 1;
        } else {
            return 0;
        }
    }

    public function fetchNameList()
    {
        $this->selectDB(rankDB);
        $names = $this->_redisConn->sMembers($this->nameSetKey());
        return $names;
    }

    public function getWhiteList()
    {
        $this->selectDB(rankDB);
        $whiteMembers = $this->_redisConn->sMembers($this->whiteListKey());
        return $whiteMembers;
    }

    public function getFormattedWhiteList()
    {
        $whiteList = $this->getWhiteList();
        $whiteString = '';
        foreach ($whiteList as $white) {
            $whiteString = $whiteString . 'U ' . $white . "\r\n";
        }
        if ($whiteString == '') {
            return 'U 256.256.256.256';
        } else {
            return $whiteString;
        }
    }

    public function deleteByName($name)
    {
//        return $deleteCount;
    }

}
