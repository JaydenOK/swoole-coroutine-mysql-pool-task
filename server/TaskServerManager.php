<?php

namespace module\server;

use module\task\AmazonModel;
use module\task\TaskFactory;

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
    private $processPrefix = 'co-server-';
    private $setting = ['worker_num' => 2, 'enable_coroutine' => true];
    /**
     * @var bool
     */
    private $daemon;
    /**
     * @var string
     */
    private $pidFile;

    public function run($argv)
    {
        try {
            $cmd = isset($argv[1]) ? (string)$argv[1] : 'status';
            $this->taskType = isset($argv[2]) ? (string)$argv[2] : '';
            $this->port = isset($argv[3]) ? (string)$argv[3] : 9901;
            $this->daemon = isset($argv[4]) && (in_array($argv[4], ['daemon', 'd', '-d'])) ? true : false;
            if (empty($this->taskType) || empty($this->port) || empty($cmd)) {
                throw new \InvalidArgumentException('params error');
            }
            $this->pidFile = $this->taskType . '.pid';
            TaskFactory::factory($this->taskType);
            switch ($cmd) {
                case 'start':
                    $this->start();
                    break;
                case 'stop':
                    $this->stop();
                    break;
                case 'status':
                    $this->status();
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            $this->logMessage('Exception:' . $e->getMessage());
        }
    }

    private function start()
    {
        //一键协程化，使mysql连接协程化
        \Swoole\Coroutine::set(['hook_flags' => SWOOLE_HOOK_ALL]);
        //\Swoole\Runtime::enableCoroutine(SWOOLE_HOOK_ALL);
        $this->renameProcessName($this->processPrefix . $this->taskType);
        $this->httpServer = new \Swoole\Http\Server("0.0.0.0", $this->port, SWOOLE_BASE);
        $setting = [
            'daemonize' => (bool)$this->daemon,
            'log_file' => MODULE_DIR . '/logs/server-' . date('Y-m') . '.log',
            'pid_file' => MODULE_DIR . '/logs/' . $this->pidFile,
        ];
        $this->setServerSetting($setting);
        $this->bindEvent(self::EVENT_WORKER_START, [$this, 'onWorkerStart']);
        $this->bindEvent(self::EVENT_REQUEST, [$this, 'onRequest']);
        $this->startServer();
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
        echo 'done' . PHP_EOL;
    }

    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        $this->logMessage('server worker start, master_pid:' . $server->master_pid);
        //初始化连接池

    }

    public function onRequest(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        try {
            $concurrency = isset($request->get['concurrency']) ? (int)$request->get['concurrency'] : 5;  //并发数
            $total = isset($request->get['total']) ? (int)$request->get['total'] : 100;  //需总处理记录数
            $taskType = isset($request->get['task_type']) ? (string)$request->get['task_type'] : '';  //任务类型
            if ($concurrency <= 0 || empty($taskType)) {
                throw new \InvalidArgumentException('parameters error');
            }
            //数据库配置信息
            $taskModel = TaskFactory::factory($taskType);
            $taskList = $taskModel->getTaskList(['limit' => $total]);
            if (empty($taskList)) {
                throw new \InvalidArgumentException('no tasks waiting to be executed');
            }
            $taskCount = count($taskList);
            $startTime = time();
            $this->logMessage("task count:{$taskCount}");
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
            $taskModel = TaskFactory::factory($taskType);
            go(function () use ($taskChan, $producerChan, $dataChan) {
                while (true) {
                    $chanStatsArr = $taskChan->stats(); //queue_num 通道中的元素数量
                    if (!isset($chanStatsArr['queue_num']) || $chanStatsArr['queue_num'] == 0) {
                        //queue_num 通道中的元素数量
                        $this->logMessage('finish deliver');
                        break;
                    }
                    //阻塞获取
                    $producerChan->pop();
                    $task = $taskChan->pop();
                    //同级协程，使用channel传递数据，
                    go(function () use ($producerChan, $dataChan, $task) {
                        //每个协程，创建独立连接（可从连接池获取）
                        //$taskModel = $this->pool->get();
                        $taskModel = TaskFactory::factory($task['task_type']);
                        $this->logMessage('taskRun:' . $task['id']);
                        $responseBody = $taskModel->taskRun($task['id'], $task);
                        $this->logMessage("task finish:{$task['id']}");
                        $pushStatus = $dataChan->push(['id' => $task['id'], 'data' => $responseBody]);
                        if ($pushStatus !== true) {
                            $this->logMessage('push errCode:' . $dataChan->errCode);
                        }
                        //处理完，恢复producerChan协程
                        $producerChan->push(1);
                        //$taskModel = $this->pool->put();
                        $taskModel = null;
                    });
                }
            });
            //消费数据
            for ($i = 1; $i <= $taskCount; $i++) {
                //阻塞，等待投递结果, 通道被关闭时，执行失败返回 false,
                $receiveData = $dataChan->pop();
                if ($receiveData === false) {
                    $this->logMessage('channel close, pop errCode:' . $dataChan->errCode);
                    //退出
                    break;
                }
                $this->logMessage('taskDone:' . $receiveData['id']);
                $taskModel->taskDone($receiveData['id'], $receiveData['data']);
            }
            //返回响应
            $endTime = time();
            $return = ['taskCount' => $taskCount, 'concurrency' => $concurrency, 'useTime' => ($endTime - $startTime) . 's'];
        } catch (\InvalidArgumentException $e) {
            $return = json_encode(['Exception' => $e->getMessage()]);
        } catch (\Exception $e) {
            $this->logMessage('Exception:' . $e->getMessage());
            $return = json_encode(['Exception' => $e->getMessage()]);
        }
        $taskModel = null;
        return $response->end(json_encode($return));
    }

    private function logMessage($logData = '')
    {
        $logData = (is_array($logData) || is_object($logData)) ? json_encode($logData, JSON_UNESCAPED_UNICODE) : $logData;
        echo date('[Y-m-d H:i:s]') . $logData . PHP_EOL;
    }

    private function stop($force = false)
    {
        $pidFile = MODULE_DIR . '/logs/' . $this->pidFile;
        if (!file_exists($pidFile)) {
            throw new \Exception('server not running');
        }
        $pid = file_get_contents($pidFile);
        if (!\Swoole\Process::kill($pid, 0)) {
            unlink($pidFile);
            throw new \Exception("pid not exist:{$pid}");
        } else {
            if ($force) {
                \Swoole\Process::kill($pid, SIGKILL);
            } else {
                \Swoole\Process::kill($pid);
            }
        }
    }

    private function status()
    {
        $pidFile = MODULE_DIR . '/logs/' . $this->pidFile;
        if (!file_exists($pidFile)) {
            throw new \Exception('server not running');
        }
        $pid = file_get_contents($pidFile);
        if (!\Swoole\Process::kill($pid, 0)) {
            echo 'not running, pid:' . $pid . PHP_EOL;
        } else {
            echo 'running, pid:' . $pid . PHP_EOL;
        }
    }


}