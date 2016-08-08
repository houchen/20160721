# encoding=utf-8
import random
from randomIP import getRandomIP
import urllib, urllib2, json
from urllib import urlencode
from word import words,ua
import sys
import time
reload(sys)
sys.setdefaultencoding('utf-8')
websitprefix = ['www.', 'mail.', 'bbs.']
websitpsuffix = ['.com', '.cn', '.org']
#api for test
url = 'http://localhost:8088/20160721/add.php'


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


weblist = open('weblist.txt', 'w')
for i in range(1, 100):
    randomweb = randomwebsite()
    weblist.write(randomweb)
    weblist.write('\n')
    webdata = open(randomweb, 'w')
    ip_str = random.choice(RANDOM_IP_POOL)
    for k in range(1, random.randint(2, 15)):  # 给这些网站生成随机的时间内的数据
        randomtime = data_time + random.randint(-604800, 0)
        rank_str = ''
        for ip in range(random.randint(10, 40)):  # 生成随机时间内的ip
            rank_str += '    "' + getRandomIP(ip_str) + '":' + str(random.randint(100, 5000)) + ',\n'
        rank_str = rank_str[:-2] + '\n'
        json = '{"name":"' + randomweb + ':ip",\n"time":' + str(
            randomtime) + ',\n"rank":{\n' + rank_str + '}}\n\n'
        addip(json)
        webdata.write(json)
    webdata.close()

    for k in range(1, random.randint(2, 10)):  # 给这些网站生成随机的时间内的数据
        randomtime = data_time + random.randint(-604800, 0)
        rank_str = ''
        for ip in range(random.randint(10, 40)):  # 生成随机时间内的ip
            rank_str += '    "' + random.choice(ua) + '":' + str(random.randint(100, 5000)) + ',\n'
        rank_str = rank_str[:-2] + '\n'
        json = '{"name":"' + randomweb + ':ua",\n"time":' + str(
            randomtime) + ',\n"rank":{\n' + rank_str + '}}\n\n'
        addip(json)
    # f2.flush()
weblist.close()
