# encoding=utf-8

from randomIP import getRandomIP
from hashlib import md5
import random
from word import words
import redis
import time

#
# nodeCount = {'6379': {'hashCode': set(), 'dataSet': redis.Redis(port='6379')},
#              '6380': {'hashCode': set(), 'dataSet': redis.Redis(port='6380')},
#              '6381': {'hashCode': set(), 'dataSet': redis.Redis(port='6381')},
#              '6382': {'hashCode': set(), 'dataSet': redis.Redis(port='6382')},
#              '6383': {'hashCode': set(), 'dataSet': redis.Redis(port='6383')}}
nodeCount = {}

nodes = [
    '6379',
    '6380',
    '6381',
    '6382'
]

print nodeCount
virtualNodeNum = 65536  # 节点非常少的时候才会用得到
perHostVirtualNodeNum = 5
usedSlots = {}


def init():
    for i in range(0, virtualNodeNum):
        usedSlots[i] = dict()
        usedSlots[i]['ip'] = '0.0.0.0'
        usedSlots[i]['used'] = False

    for node in nodes:
        addNode(node)


def addNode(node):
    nodeCount[node] = dict()
    nodeCount[node]['hashCode'] = set()
    nodeCount[node]['dataSet'] = redis.Redis(port=int(node))
    nodeCount[node]['dataSet'].flushall()
    nodeCount[node]['cacheMiss'] = 0
    nodeCount[node]['get'] = 0
    nodeCount[node]['set'] = 0
    for i in range(0, perHostVirtualNodeNum):
        while True:
            nodeHash = int('0x' + md5(node + '#' + str(random.randint(0, virtualNodeNum))).hexdigest(),
                           0) % virtualNodeNum
            # nodeHash = random.randint(0, virtualNodeNum - 1)
            if not usedSlots[nodeHash]['used']:
                break
        nodeCount[node]['hashCode'].add(nodeHash)
        usedSlots[nodeHash]['ip'] = node
        usedSlots[nodeHash]['used'] = True


def remNode(node):
    for hashcode in nodeCount[node]['hashCode']:
        usedSlots[hashcode]['ip'] = '-1'
        usedSlots[hashcode]['used'] = False
        # del nodeCount[node]['hashCode']
        # del nodeCount[node]['dataSet']


def getKeyNode(key):
    keyHash = int('0x' + md5(str(key)).hexdigest(), 0) % virtualNodeNum
    while True:
        if usedSlots[keyHash]['used']:
            ip = usedSlots[keyHash]['ip']
            return ip
        else:
            keyHash = (keyHash + 1) % virtualNodeNum


def getValue(key):
    keyHash = int('0x' + md5(str(key)).hexdigest(), 0) % virtualNodeNum
    while True:
        if usedSlots[keyHash]['used']:
            ip = usedSlots[keyHash]['ip']
            value = nodeCount[ip]['dataSet'].get(key)
            nodeCount[ip]['get'] += 1
            if value is None:
                nodeCount[ip]['cacheMiss'] += 1
            return value
        else:
            keyHash = (keyHash + 1) % virtualNodeNum


def setValue(key, value):
    keyHash = int('0x' + md5(str(key)).hexdigest(), 0) % virtualNodeNum
    while True:
        if usedSlots[keyHash]['used']:
            ip = usedSlots[keyHash]['ip']
            ret = nodeCount[ip]['dataSet'].set(key, value)
            if ret is True:
                nodeCount[ip]['set'] += 1
            return
        else:
            keyHash = (keyHash + 1) % virtualNodeNum


init()

print nodeCount

for used in usedSlots:
    if usedSlots[used]['used']:
        print 'ip=', used, 'content=', usedSlots[used]

for i in range(1, 2):
    setValue(100, random.randint(1, 1000000))

keyNode = getKeyNode(100)
print keyNode
print nodeCount
addNode('6383')

# for node in nodes:
#     if node != keyNode:
#         print 'remove node ',node
#         remNode(node)

print nodeCount
print getValue(100)
remNode('6383')
print nodeCount
print getValue(100)
exit()

for i in range(1, 100000):
    if i == 33000:
        addNode('6383')
        print nodeCount
    if i == 66000:
        remNode('6379')
        print nodeCount
    if i % 10 == 0:
        setValue(i, random.randint(0, 1000000))
    else:
        getValue(i)

for i in nodeCount:
    print 'port=', i, 'dbsize=', nodeCount[i]['dataSet'].dbsize()

print nodeCount
