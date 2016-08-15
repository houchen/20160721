# encoding=utf-8
import random
import redis
import sys
import time
from word import words;

reload(sys)
sys.setdefaultencoding('utf-8')
websitprefix = ['www.', 'mail.', 'bbs.']
websitpsuffix = ['.com', '.cn', '.org']
RANDOM_IP_POOL = ['192.168.10.0/24', '172.16.0.0/16', '192.168.1.0/24', '192.168.2.0/24']


def timestamp_datetime(value):
    format = '%Y-%m-%d %H:%M:%S'
    # value为传入的值为时间戳(整形)，如：1332888820
    value = time.localtime(value)
    ## 经过localtime转换后变成
    # 最后再经过strftime函数转换为正常日期格式。
    dt = time.strftime(format, value)
    return dt


def datetime_timestamp(dt):
    # dt为字符串
    # 中间过程，一般都需要将字符串转化为时间数组
    time.strptime(dt, '%Y-%m-%d %H:%M:%S')
    # 将"2012-03-28 06:53:40"转化为时间戳
    s = time.mktime(time.strptime(dt, '%Y-%m-%d %H:%M:%S'))
    return int(s)


data_time = time.time()

re = redis.Redis()
for i in range(0, 1000):
    re.zadd('remlex_test', str(data_time + i) + ':' + random.choice(words),
            1)
