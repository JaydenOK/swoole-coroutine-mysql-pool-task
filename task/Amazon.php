<?php

namespace module\task;

class Amazon extends TaskModel implements Task
{

    const TYPE = 'Amazon';

    public function identity()
    {
        return 'yibai_amazon_account';
    }

    public function getTaskList($params)
    {
        // TODO: Implement getTaskList() method.
        $result = $this->query->from($this->tableName())->where('id<', 1000)->limit($params['limit'])->fetchAll();
        return $result;
    }

    public function runTask($task)
    {
        // TODO: Implement runTask() method.
        $id = $task['id'];
        $appId = $task['app_id'];
        $sellingPartnerId = $task['selling_partner_id'];
        $host = 'api.amazon.com';
        $path = '/auth/o2/token';
        $data = [];
        $data['grant_type'] = 'refresh_token';
        $data['client_id'] = '111';
        $data['client_secret'] = '222';
        $data['refresh_token'] = '333';
        $cli = new \Swoole\Coroutine\Http\Client($host, 443, true);
        $cli->set(['timeout' => 10]);
        $cli->setHeaders([
            'Host' => $host,
            'grant_type' => 'refresh_token',
            'client_id' => 'refresh_token',
            "User-Agent" => 'Chrome/49.0.2587.3',
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8',
        ]);
        $cli->post($path, http_build_query($data));
        $responseBody = $cli->body;
        return $cli->body;
    }

    public function taskCallback($data)
    {
        // TODO: Implement taskCallback() method.
        $db->where(['id' => $id])->set(['refresh_msg' => json_encode($responseBody, 256), 'refresh_time' => date('Y-m-d H:i:s')])->update('yibai_amazon_account');
    }


}