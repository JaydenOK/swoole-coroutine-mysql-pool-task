<?php

namespace module\task;

class Shopee extends TaskModel implements Task
{

    const TYPE = 'Shopee';

    public function identity()
    {
        return 'yibai_shopee_account';
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
        $host = 'partner.shopeemobile.com';
        $timestamp = time();
        $path = '/api/v2/auth/access_token/get';
        $sign = '111';
        $data = [];
        $data['partner_id'] = 111;
        $data['refresh_token'] = '222';
        $data['merchant_id'] = 333;
        $path .= '?timestamp=' . $timestamp . '&sign=' . $sign . '&partner_id=' . $data['partner_id'];
        $cli = new \Swoole\Coroutine\Http\Client($host, 443, true);
        $cli->set(['timeout' => 10]);
        $cli->setHeaders([
            'Host' => $host,
            'Content-Type' => 'application/json;charset=UTF-8',
        ]);
        $data = [];
        $cli->post($path, json_encode($data));
        $responseBody = $cli->body;
        return $responseBody;
    }

    public function taskCallback($data)
    {
        // TODO: Implement taskCallback() method.
        $db->where(['id' => $id])->set(['refresh_msg' => json_encode($responseBody, 256), 'refresh_time' => date('Y-m-d H:i:s')])->update('yibai_shopee_account');
    }


}