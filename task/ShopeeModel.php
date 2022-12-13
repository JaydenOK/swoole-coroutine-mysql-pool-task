<?php

namespace module\task;

class ShopeeModel extends TaskModel
{

    public function tableName()
    {
        return 'yibai_shopee_account';
    }

    public function getTaskList($params)
    {
        // TODO: Implement getTaskList() method.
        $result = $this->query->where('id', 5000, '<')->get($this->tableName(), $params['limit']);
        return $result;
    }

    public function taskRun($id, $task)
    {
        // TODO: Implement runTask() method.
        $data = ['refresh_num' => mt_rand(0, 10)];
        $res = $this->query->where('id', $task['id'])->update($this->tableName(), $data);
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

    public function taskDone($id, $data)
    {
        // TODO: Implement taskCallback() method.
        $data = ['refresh_msg' => json_encode($data, 256), 'refresh_time' => date('Y-m-d H:i:s')];
        $res = $this->query->where('id', $id)->update($this->tableName(), $data);
    }


}