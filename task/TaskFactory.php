<?php

namespace module\task;
/**
 * 工厂方法，生产任务模型
 * Class TaskFactory
 * @package module\task
 */
class TaskFactory
{
    const TASK_AMAZON = 'Amazon';
    const TASK_SHOPEE = 'Shopee';
    const TASK_EBAY = 'Ebay';

    /**
     * @param $taskType
     * @return TaskModel
     * @throws \Exception
     */
    public static function factory($taskType)
    {
        $task = null;
        switch ($taskType) {
            case self::TASK_AMAZON:
                $task = new AmazonModel();
                break;
            case self::TASK_SHOPEE:
                $task = new ShopeeModel();
                break;
            default:
                break;
        }
        if ($task === null) {
            throw new \Exception('task model not defined');
        }
        return $task;
    }

}