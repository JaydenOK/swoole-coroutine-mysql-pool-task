<?php
/**
 * Coroutine协程并发实例，适用于内部系统要处理大量耗时的任务
 * 常驻监听进程启动，Http Server + 协程 + channel 实现并发处理，可控制并发数量，分批次执行任务
 *
 */

error_reporting(-1);
ini_set('display_errors', 1);

require 'bootstrap.php';

$manager = new module\server\TaskServerManager();
$manager->run($argv);
