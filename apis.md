### 添加数据
- 请求地址: add.php
- 请求格式
~~~javascript
{
   "name": "foo_name:ip",//这个是唯一的名字,搜索时使用的名字
   "time": '',// 没有时间戳,系统会把接收到数据的时间作为时间戳
   "rank": {
       "A192.168.1.1": 12, //数据项目和分数
       "B172.15.1.125": 56,
       'C172.16.123.18': 10000,
       'D114.114.114.114': 4
   }
}
~~~
- 返回格式
~~~javascript
{'status':'ok'}//ok,err
~~~

请求总排名的top N：

- 请求地址： query.php?method=queryRankByName
- 参数格式： 
~~~javascript
{
   'name': 'foo_name',//查询哪个name的排名
   'start': 0,//返回从哪一名开始
   'stop': -1,//返回从哪一名结束，这里是意思是倒数第一名
    withScores: true,//是否返回ip的分数
    withTime: true,//是否返回ip最后一次加分时间，时间是时间戳
    byScore: false//按分数查询还是按排名查询，填false表明查询排名在[start,stop]的ip，填true表明查询分数在[start,stop]的ip
}
~~~
- 返回格式：
  - 当不带ip加分时间，也不带ip分数时
​	name字段和post请求的字段一致，time为当前系统时间，rank为排名数组
~~~javascript
{
    "name": "foo_name",
    "time": 1469697436,
    "rank": [
        "C172.16.123.18",
        "B172.15.1.125",
        "A192.168.1.1",
        "D114.114.114.114"
    ]
}	
~~~
   
  - 当只带有withScores／withTime其中一个选项时
name和time意义同上，rank对象的key为ip，如果是withScores选项，则value的值为分数，withTime选项则为最后一次加分时间的时间戳
~~~javascript
{
    "name": "foo_name",
    "time": 1469697638,
    "rank": {
        "C172.16.123.18": 60000,//这个可能是分数也可能是时间
        "B172.15.1.125": 336,
        "A192.168.1.1": 72,
        "D114.114.114.114": 24
    }
}	
~~~
  - 当有withScores和withTime两个选项时
  则返回的rank中，每个key为ip，value为一个数组，第一个值时分数，第二个值是时间
~~~javascript
{
    "name": "foo_name",
    "time": 1469698623,
    "rank": {
        "C172.16.123.18": [
            60000,//这个是分数
            "1469693407.599"//这个是时间
        ],
        "B172.15.1.125": [
            336,
            "1469693407.599"
        ],
        "A192.168.1.1": [
            72,
            "1469693407.599"
        ],
        "D114.114.114.114": [
            24,
            "1469693407.599"
        ]
    }
}
~~~

### 请求某时间段内的top N

- 请求地址:   query.php?method=queryRankByTimeInterval
> 这个接口要做大量的union,最好不要频繁请求。每分钟请求1小时的数据,也没有意义,因为1小时之内,排名基本也不会变化
- 参数格式
~~~javascript
{
    'name': 'foo_name',//指定了查询哪个名字的时间段排名
     start: 12312,//从哪个时间点开始,时间戳
     stop: 1232333,//截止到哪个时间点
     withScores: true,//要不要每个ip的分数
     withTime:false//要不要每个ip最后一次加分的时间点
};
~~~
- 返回格式
    > 和按排名/按分数段请求返回格式相似, 参考上面的规则

### 获取名字列表
- 请求地址:  query.php?method=getNameList
- 请求参数: 不需要其它参数
- 返回格式:
~~~javascript
[
    "foo_name",
    "foo_name2",
    "foo_name3"
]
~~~

### 查询错误
- 返回一个json格式的错误提示

### 删除纪录
- 请求地址: delete.php?name=foo_name
- 返回格式:
~~~javascript
{'count':10}//删除了10条改name的添加纪录
~~~

