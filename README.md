# swoole-coroutine-mysql-pool-task
swoole coroutine协程并发项目，mysql连接池， swoole-coroutine-mysql-pool-task 

### 功能逻辑
```text
- 启动http服务器，监听http端口（不同任务类型，启动不同端口）
- 请求回调，查询当前需要处理的总任务数；
- 将任务保存到任务channel，初始化限制并发数channel；
- 启动生产者协程投递任务，阻塞获取任务，并启动独立协程，并发处理任务；
- 任务完成，数据投递到数据channel,供消费者处理数据结果，并channel阻塞，继续投递任务到最大并发；

```