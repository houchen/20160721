### 环境
需要安装和设置:
- php的redis扩展
- 将model/ services/ static/ 目录和 add.php data.html delete.php query.php 程序
放到php服务器的web目录下
- 编辑services/redisConfig.php,设置redis的地址和端口号
- 正确运行redis

### 测试
配置完成访问data.html,无任何数据。
>测试小工具:
目录data_generator下的data_generator.py可以生成最近几天的假数据
addipAPI_test.py可以每秒添加新数据(可能需要执行
pip install redis安装python的redis客户端)

有数据后,访问data.html,输入名字过滤条件和时间条件,点击查找。有数据时则显示统计排名

### 其它
添加数据的接口说明在apis.md中


