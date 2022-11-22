<?php

namespace module\task;

use module\lib\PdoClient;

class TaskModel
{

    const TASK_AMAZON = 'Amazon';
    const TASK_SHOPEE = 'Shopee';
    /**
     * @var PdoClient
     */
    protected $pdo;
    /**
     * @var \module\FluentPDO\Query
     */
    protected $query;

    public function __construct()
    {
        $this->pdo = new PdoClient();
        $this->query = $this->pdo->getQuery();
    }

    /**
     * @param $taskType
     * @return Amazon|Shopee|null
     * @throws \Exception
     */
    public static function factory($taskType)
    {
        $task = null;
        switch ($taskType) {
            case self::TASK_AMAZON:
                $task = new Amazon();
                break;
            case self::TASK_SHOPEE:
                $task = new Shopee();
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