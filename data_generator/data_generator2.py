# encoding=utf-8

import random
from randomIP import getRandomIP
import urllib, urllib2, json
from urllib import urlencode
from word import words, ip, timestamp
import sys
import time

reload(sys)
sys.setdefaultencoding('utf-8')
websitprefix = ['www.', 'mail.', 'bbs.']
websitpsuffix = ['.com', '.cn', '.org']
# api for test
url = 'http://localhost:8088/20160721-fix/add.php'

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


data_time = int(time.time())


def randomwebsite():
    web = random.choice(websitprefix) + random.choice(words) + (random.choice(websitpsuffix))
    print web
    return web


def addip(ip_json):
    headers = {'Content-Type': 'text/json;charset=UTF-8'}
    # jdata = json.dumps(ip_json)
    # req = urllib2.Request(url, jdata)
    # response = urllib2.urlopen(req)
    # return response.read()
    payload = {'data': ip_json}
    r = urllib2.urlopen(url=url, data=urllib.urlencode(payload))
    # r = requests.post(url, data=payload, headers=headers)
    print r.read()

for name in words:
    for ip_str in ip:
        for timeDelta in timestamp:
            time = data_time - timeDelta
            for score in range(1, 20):
                rank_str = '    "' + ip_str + '":' + str(score) + ',\n'
                rank_str = rank_str[:-2] + '\n'
                json = '{"name":"' + name + ':ip",\n"time":' + str(
                    time) + ',\n"rank":{\n' + rank_str + '}}\n\n'
                addip(json)
