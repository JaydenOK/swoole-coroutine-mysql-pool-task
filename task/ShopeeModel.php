<?php

namespace module\task;

class ShopeeModel extends TaskModel
{

    const TYPE = 'Shopee';

    public function tableName()
    {
        return 'yibai_shopee_account';
    }

    public function getTaskList($params)
    {
        // TODO: Implement getTaskList() method.
        $result = $this->query->from($this->tableName())->where('id<', 5000)->limit($params['limit'])->fetchAll();
        return $result;
    }

    public function runTask($id, $task)
    {
        // TODO: Implement runTask() method.
        $this->query->update($this->tableName())->set('refresh_num', mt_rand(0, 10))->where('id', $task['id'])->execute();
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

    public function taskCallback($id, $data)
    {
        // TODO: Implement taskCallback() method.
        $this->query->update($this->tableName())->set(['refresh_msg' => json_encode($data, 256), 'refresh_time' => date('Y-m-d H:i:s')])->where('id', $id)->execute();
    }


}