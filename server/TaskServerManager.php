<?php
/**
 * 多进程消费者管理实例
 * 功能 : 使用多进程，启动多个rabbitMQ消费者，消费队列数据
 */

namespace module\server;

use module\task\TaskModel;

class TaskServerManager
{
    const EVENT_WORKER_START = 'workerStart';
    const EVENT_REQUEST = 'request';
    /**
     * @var \Swoole\Http\Server
     */
    protected $httpServer;
    /**
     * @var string
     */
    private $taskType;
    /**
     * @var int|string
     */
    private $port;
    private $processPrefix = 'co-http-server-';
    /**
     * @var \module\task\Task
     */
    private $taskModel;
    private $setting = ['enable_coroutine' => true];

    public function run($argv)
    {
        try {
            $this->taskType = isset($argv[1]) ? (string)$argv[1] : '';
            $this->port = isset($argv[2]) ? (string)$argv[2] : 9901;
            if (empty($this->taskType) || empty($this->port)) {
                throw new \InvalidArgumentException('params error');
            }
            $this->taskModel = TaskModel::factory($this->taskType);
            $this->renameProcessName($this->processPrefix . $this->taskType);
            $this->httpServer = new \Swoole\Http\Server("0.0.0.0", $this->port, SWOOLE_BASE);
            $setting = [];
            $this->setServerSetting($setting);
            $this->bindEvent(self::EVENT_WORKER_START, [$this, 'onWorkerStart']);
            $this->bindEvent(self::EVENT_REQUEST, [$this, 'onRequest']);
            $this->startServer();
        } catch (\Exception $e) {
            $this->writeLog($e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 当前进程重命名
     * @param $processName
     * @return bool|mixed
     */
    private function renameProcessName($processName)
    {
        if (function_exists('cli_set_process_title')) {
            return cli_set_process_title($processName);
        } else if (function_exists('swoole_set_process_name')) {
            return swoole_set_process_name($processName);
        }
        return false;
    }

    private function setServerSetting($setting = [])
    {
        //开启内置协程，默认开启
        //当 enable_coroutine 设置为 true 时，底层自动在 onRequest 回调中创建协程，开发者无需自行使用 go 函数创建协程
        //当 enable_coroutine 设置为 false 时，底层不会自动创建协程，开发者如果要使用协程，必须使用 go 自行创建协程
        $this->httpServer->set(array_merge($this->setting, $setting));
    }

    private function bindEvent($event, callable $callback)
    {
        $this->httpServer->on($event, $callback);
    }

    private function startServer()
    {
        $this->httpServer->start();
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        echo 'worker start:' . $workerId . PHP_EOL;
    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        try {
            $concurrency = isset($request->get['concurrency']) ? (int)$request->get['concurrency'] : 5;  //并发数
            $total = isset($request->get['total']) ? (int)$request->get['total'] : 100;  //需总处理记录数
            $taskType = isset($request->get['task_type']) ? (string)$request->get['task_type'] : '';  //任务类型
            if ($concurrency <= 0 || empty($taskType)) {
                return $response->end('error params');
            }
            //数据库配置信息
            $this->taskModel = TaskModel::factory($taskType);
            $taskList = $this->taskModel->getTaskList(['limit' => $total]);
            if (empty($taskList)) {
                return $response->end('not task wait');
            }
            $taskCount = count($taskList);
            $startTime = time();
            echo "task count:{$taskCount}" . PHP_EOL;
            $taskChan = new \chan($taskCount);
            //初始化并发数量
            $producerChan = new \chan($concurrency);
            $dataChan = new \chan($total);
            for ($size = 1; $size <= $concurrency; $size++) {
                $producerChan->push(1);
            }
            foreach ($taskList as $task) {
                //增加当前任务类型标识
                $task = array_merge($task, ['task_type' => $taskType]);
                $taskChan->push($task);
            }
            //创建生产者协程，投递任务
            //创建协程处理请求
            go(function () use ($taskChan, $producerChan, $dataChan) {
                while (true) {
                    $chanStatsArr = $taskChan->stats(); //queue_num 通道中的元素数量
                    if (!isset($chanStatsArr['queue_num']) || $chanStatsArr['queue_num'] == 0) {
                        //queue_num 通道中的元素数量
                        echo 'chanStats:' . json_encode($chanStatsArr, 256) . PHP_EOL;
                        break;
                    }
                    //阻塞获取
                    $producerChan->pop();
                    $task = $taskChan->pop();
                    //每个协程，创建独立连接（可从连接池获取）
                    $taskModel = TaskModel::factory($task['task_type']);
                    go(function () use ($producerChan, $dataChan, $task, $taskModel) {
                        echo 'producer:' . $task['id'] . PHP_EOL;
                        $responseBody = $taskModel->runTask($task['id'], $task);
                        echo 'deliver:' . $task['id'] . PHP_EOL;
                        $pushStatus = $dataChan->push(['id' => $task['id'], 'data' => $responseBody]);
                        if ($pushStatus !== true) {
                            echo 'push errCode:' . $dataChan->errCode . PHP_EOL;
                        }
                        //处理完，恢复producerChan协程
                        $producerChan->push(1);
                        echo "producer:{$task['id']} done" . PHP_EOL;
                    });
                }
            });
            //消费数据
            for ($i = 1; $i <= $taskCount; $i++) {
                //阻塞，等待投递结果, 通道被关闭时，执行失败返回 false,
                $receiveData = $dataChan->pop();
                if ($receiveData === false) {
                    echo 'pop errCode:' . $dataChan->errCode . PHP_EOL;
                    //退出
                    break;
                }
                echo 'receive:' . $receiveData['id'] . PHP_EOL;
                $this->taskModel->taskCallback($receiveData['id'], $receiveData['data']);
            }
            //返回响应
            $endTime = time();
            $return = ['taskCount' => $taskCount, 'concurrency' => $concurrency, 'useTime' => ($endTime - $startTime) . 's'];
            return $response->end(json_encode($return));
        } catch (\Swoole\ExitException $e) {
            return $response->end(json_encode($e->getMessage()));
        }
    }

    /**
     * @param string $logData
     */
    private function writeLog($logData = '')
    {
        $logFile = MODULE_DIR . '/logs/server-' . date('Y-m') . '.log';
        $logData = (is_array($logData) || is_object($logData)) ? json_encode($logData, JSON_UNESCAPED_UNICODE) : $logData;
        file_put_contents($logFile, date('[Y-m-d H:i:s]') . $logData . PHP_EOL, FILE_APPEND);
    }

}