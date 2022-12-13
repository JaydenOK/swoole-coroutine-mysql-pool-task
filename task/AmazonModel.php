<?php

namespace module\task;

class AmazonModel extends TaskModel
{

    public function tableName()
    {
        return 'yibai_amazon_account';
    }

    public function getTaskList($params)
    {
        // TODO: Implement getTaskList() method.
        //$result = $this->query->where('id', 5000, '<')->get($this->tableName(), $params['limit']);      //查所有
        $result = $this->query->where('id', 5000, '<')->page(1)->limit($params['limit'])->paginate($this->tableName());
        return $result;
    }

    /**
     * 重新解压，编译支持https
     * phpize && ./configure --enable-openssl --enable-http2 && make && sudo make install
     * @param $id
     * @param $task
     * @return mixed
     * @throws \Exception
     */
    public function taskRun($id, $task)
    {
        // TODO: Implement runTask() method.
        //todo 模拟业务耗时处理逻辑
        $data = ['refresh_num' => mt_rand(0, 10)];
        $res = $this->query->where('id', $task['id'])->update($this->tableName(), $data);

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

    public function taskDone($id, $data)
    {
        // TODO: Implement taskCallback() method.
        $data = ['refresh_msg' => json_encode($data, 256), 'refresh_time' => date('Y-m-d H:i:s')];
        $res = $this->query->where('id', $id)->update($this->tableName(), $data);
    }


}