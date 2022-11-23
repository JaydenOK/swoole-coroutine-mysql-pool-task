<?php

namespace module\task;

use module\lib\PdoClient;

abstract class TaskModel implements Task
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
     * @return AmazonModel|ShopeeModel|null
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