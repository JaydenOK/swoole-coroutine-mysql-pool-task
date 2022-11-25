<?php

namespace module\task;

class TaskFactory
{
    const TASK_AMAZON = 'Amazon';
    const TASK_SHOPEE = 'Shopee';

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