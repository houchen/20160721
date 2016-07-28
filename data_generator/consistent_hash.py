from randomIP import getRandomIP
from hashlib import md5
import random
from word import words
import redis

nodeCount = {'6379': {'hashCode': set(), 'dataSet': redis.Redis(port='6379')},
             '6380': {'hashCode': set(), 'dataSet': redis.Redis(port='6380')},
             '6381': {'hashCode': set(), 'dataSet': redis.Redis(port='6381')},
             '6382': {'hashCode': set(), 'dataSet': redis.Redis(port='6382')},
             '6383': {'hashCode': set(), 'dataSet': redis.Redis(port='6383')}}

print nodeCount
virtualNodeNum = 32#节点非常少的时候才会用得到
perHostVirtualNodeNum = virtualNodeNum/2
usedSlots = {}


def init():
    for i in range(0, virtualNodeNum):
        usedSlots[i] = dict()
        usedSlots[i]['ip'] = '0.0.0.0'
        usedSlots[i]['used'] = False

    for ipKey in nodeCount:
        #在节点非常少的时候,这里选择物理节点的hash就会有冲突
        for i in range(1, virtualNodeNum /):
            # nodeHash = int('0x' + md5(ipKey + '#' + str(i)).hexdigest(), 0) % virtualNodeNum
            while True:
                nodeHash = random.randint(0, virtualNodeNum - 1)
                if not usedSlots[nodeHash]['used']:
                    break
            nodeCount[ipKey]['hashCode'].add(nodeHash)
            usedSlots[nodeHash]['ip'] = ipKey
            usedSlots[nodeHash]['used'] = True
            # already in used


print nodeCount, '\n'

print usedSlots, '\n'


def addNode(node):
    for i in range(1, virtualNodeNum / len(nodeCount)):
        # nodeHash = int('0x' + md5(node + '#' + str(i)).hexdigest(), 0) % virtualNodeNum
        while True:
            nodeHash = random.randint(0, virtualNodeNum - 1)
            if not usedSlots[nodeHash]['used']:
                break
        if not usedSlots[nodeHash]['used']:
            nodeCount[node] = dict()
            nodeCount[node]['hashCode'] = set()
            nodeCount[node]['hashCode'].add(nodeHash)
            nodeCount[node]['dataSet'] = redis.Redis(port=node)
            usedSlots[nodeHash]['ip'] = node
            usedSlots[nodeHash]['used'] = True
        else:
            pass


def remNode(node):
    for i in range(1, virtualNodeNum / len(nodeCount)):
        nodeHash = int('0x' + md5(node + '#' + str(i)).hexdigest(), 0) % virtualNodeNum
        usedSlots[nodeHash]['ip'] = '0.0.0.0'
        usedSlots[nodeHash]['used'] = False
        del nodeCount[node]['hashCode']
        del nodeCount[node]['dataSet']


def getValue(key):
    pass


def setValue(key, value):
    pass


init()
# m1 = getRandomIP('192.168.10.0/24')
# m1Hash = int('0x' + md5(d1).hexdigest(), 0) % nodeNum
for i in range(1, 100):
    d1 = random.randint(0, 1000000)
    d1Hash = d1 % nodeNum
    nodeCount[nodeList[d1Hash]].add(d1)

nodeList.append('192.168.10.50')
nodeCount['192.168.10.50'] = set()
nodeNum = len(nodeList)
nodeLoad *= nodeNum
for i in range(1, 600):
    d1 = random.randint(0, 1000000)
    d1Hash = d1 % nodeNum
    nodeCount[nodeList[d1Hash]].add(d1)

i = 0
for ip in nodeList:
    print ip, '->', len(nodeCount[ip]), '\n'
    print nodeCount[ip], '\n'
    i += 1
