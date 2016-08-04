### 环境
- php
- php的redis扩展
- 在services/redisConfig.php配置redis的地址和端口号

### 测试
配置完成访问data.html,无任何数据。
目录data_generator下的data_generator.py可以生成最近几天的
假数据,addipAPI_test.py可以每秒添加新数据
添加数据后,在data.html下拉访问。

### 其它
调接口的json格式和请求地址在apis.md中


