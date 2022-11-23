<?php
/**
 * Coroutine协程并发实例，适用于内部系统要处理大量耗时的任务
 *
 * 常驻监听进程启动，Http Server + 协程 + channel 实现并发处理，可控制并发数量，分批次执行任务
 *
 * 并发请求亚马逊。虾皮电商平台接口，测试结果如下
 *
 * [root@ac_web ]# php service.php Amazon 9901
 *
 * [root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=5&total=200"
 * {"taskCount":200,"concurrency":5,"useTime":"56s"}
 *
 * [root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=10&total=200"
 * {"taskCount":200,"concurrency":10,"useTime":"28s"}
 *
 * [root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=20&total=200"
 * {"taskCount":200,"concurrency":20,"useTime":"10s"}
 *
 * [root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=50&total=200"
 * {"taskCount":200,"concurrency":50,"useTime":"6s"}
 *
 * [root@ac_web ]# curl "127.0.0.1:9901/?task_type=Amazon&concurrency=200&total=500"
 * {"taskCount":500,"concurrency":200,"useTime":"3s"}
 */

error_reporting(-1);
ini_set('display_errors', 1);

require 'bootstrap.php';

$manager = new module\server\TaskServerManager();
$manager->run($argv);
