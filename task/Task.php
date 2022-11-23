<?php

namespace module\task;

interface Task
{
    public function tableName();

    public function getTaskList($params);

    public function runTask($id, $task);

    public function taskCallback($id, $data);
}