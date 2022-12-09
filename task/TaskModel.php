<?php

namespace module\task;

use module\lib\PdoClient;

abstract class TaskModel implements Task
{

    /**
     * @var PdoClient
     */
    protected $pdoClient;
    /**
     * @var \module\FluentPDO\Query
     */
    protected $query;

    public function __construct()
    {
        $this->pdoClient = new PdoClient();
        $this->query = $this->pdoClient->getQuery();
    }

    public function __destruct()
    {
        $this->query->close();
        $this->query = null;
        $this->pdoClient = null;
        //echo date('[Y-m-d H:i:s]') . 'query close' . PHP_EOL;
    }

}