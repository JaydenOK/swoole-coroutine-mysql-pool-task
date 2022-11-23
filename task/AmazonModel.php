<?php

namespace module\task;

class AmazonModel extends TaskModel
{

    const TYPE = 'Amazon';

    public function tableName()
    {
        return 'yibai_amazon_account';
    }

    public function getTaskList($params)
    {
        // TODO: Implement getTaskList() method.
        $result = $this->query->from($this->tableName())->where('id<?', 5000)->limit($params['limit'])->fetchAll();
        return $result;
    }

    /**
     * 重新解压，编译支持https
     * phpize && ./configure --enable-openssl --enable-http2 && make && sudo make install
     * @param $id
     * @param $task
     * @return mixed
     * @throws \module\FluentPDO\Exception
     */
    public function runTask($id, $task)
    {
        // TODO: Implement runTask() method.
        //todo 模拟业务耗时处理逻辑
        $this->query->update($this->tableName())->set('refresh_num', mt_rand(0, 10))->where('id', $task['id'])->execute();

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
        return $responseBody;
    }

    public function taskCallback($id, $data)
    {
        // TODO: Implement taskCallback() method.
        $this->query->update($this->tableName())->set(['refresh_msg' => json_encode($data, 256), 'refresh_time' => date('Y-m-d H:i:s')])->where('id', $id)->execute();
    }


}