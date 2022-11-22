<?php

namespace module\task;

interface Task
{
    public function identity();

    public function getTaskList($params);

    public function runTask($task);

    public function taskCallback($data);
}