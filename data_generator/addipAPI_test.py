# encoding=utf-8
import random
from randomIP import getRandomIP
import urllib, urllib2, json, requests
from word import words
import time

websitprefix = ['www.', 'mail.', 'bbs.']
websitpsuffix = ['.com', '.cn', '.org']

import time

RANDOM_IP_POOL = ['192.168.10.0/24', '172.16.0.0/16', '192.168.1.0/24', '192.168.2.0/24']


def timestamp_datetime(value):
    format = '%Y-%m-%d %H:%M:%S'
    # value为传入的值为时间戳(整形)，如：1332888820
    value = time.localtime(value)
    ## 经过localtime转换后变成
    ## time.struct_time(tm_year=2012, tm_mon=3, tm_mday=28, tm_hour=6, tm_min=53, tm_sec=40, tm_wday=2, tm_yday=88, tm_isdst=0)
    # 最后再经过strftime函数转换为正常日期格式。
    dt = time.strftime(format, value)
    return dt


def datetime_timestamp(dt):
    # dt为字符串
    # 中间过程，一般都需要将字符串转化为时间数组
    time.strptime(dt, '%Y-%m-%d %H:%M:%S')
    ## time.struct_time(tm_year=2012, tm_mon=3, tm_mday=28, tm_hour=6, tm_min=53, tm_sec=40, tm_wday=2, tm_yday=88, tm_isdst=-1)
    # 将"2012-03-28 06:53:40"转化为时间戳
    s = time.mktime(time.strptime(dt, '%Y-%m-%d %H:%M:%S'))
    return int(s)


#
# if __name__ == '__main__':
#     d = datetime_timestamp('2012-03-28 06:53:40')
#     print d
#     s = timestamp_datetime(1332888820)
#     print s

# data_time = datetime_timestamp('2016-07-26 09:53:40')
data_time = time.time()


def randomwebsite():
    web = random.choice(websitprefix) + random.choice(words) + (random.choice(websitpsuffix))
    print web
    return web


def addip(ip_json):
    url = 'http://localhost:8088/20160721/add.php'
    headers = {'Content-Type': 'text/json;charset=UTF-8'}
    # jdata = json.dumps(ip_json)
    # req = urllib2.Request(url, jdata)
    # response = urllib2.urlopen(req)
    # return response.read()
    payload = {'data': ip_json}
    r = urllib2.urlopen(url=url, data=urllib.urlencode(payload))
    # r = requests.post(url, data=payload, headers=headers)
    print r.read()


randomtime = data_time
while 1:
    rank_str = ''
    website = 'bbs.drawing.com'
    randomtime += 1
    ip_str = random.choice(RANDOM_IP_POOL)
    for ip_count in range(random.randint(10, 40)):
        rank_str += '    "' + getRandomIP(ip_str) + '":' + str(random.randint(100, 5000)) + ',\n'
    rank_str = rank_str[:-2] + '\n'
    json = '{"name":"' + website + '",\n"time":' + str(
        randomtime) + ',\n"rank":{\n' + rank_str + '}}\n\n'
    addip(json)
    time.sleep(1)
