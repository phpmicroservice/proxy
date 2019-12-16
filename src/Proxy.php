<?php

namespace proxy;

use Phalcon\Events\Event;
use pms\Output;

/**
 * 注册服务
 * Class Register
 * @property \pms\bear\ClientCoroutine $register_client
 * @package pms
 */
class Proxy
{
    private $client_ip;
    private $client_port;
    private $register_client;
    private $regclient_ip;
    private $regserver_port;
    private $reg_status = false;


    /**
     * 配置初始化
     */
    public function __construct(\Swoole\Server $server)
    {
        $config = \Phalcon\Di\FactoryDefault\Cli::getDefault()->getShared('config');
        $this->client_ip = $config->server->register_addr;
        $this->client_port = $config->server->register_port;
        \pms\output([$this->client_ip, $this->client_port], 'Register');
        $this->register_client = new \pms\bear\ClientCoroutine($this->client_ip, $this->client_port, 10);

        $this->ping();
    }


    /**
     * 配置更新
     */
    public function ping()
    {
        Output::info($this->register_client->isConnected(), 'ping');
        if ($this->register_client->isConnected()) {
            $data = [
                'name' => strtolower(SERVICE_NAME),
                'host' => APP_HOST_IP,
                'port' => APP_HOST_PORT,
                'type' => 'tcp'
            ];
            Output::info($data, 'ping');
            try {
                if ($this->reg_status) {
                    # 注册完毕进行ping
                    $data = $this->register_client->ask_recv('register', '/service/ping', $data);
                    # 正确的
                } else {
                    # 没有注册完毕,先注册
                    $data = $this->register_client->ask_recv('register', '/service/reg', $data);
                }
            } catch (\Throwable $exception) {
                $data = [];
            }

            # 正确的
            if ($data['t'] == '/service/reg') {
                # 我们需要的数据
                $this->reg_status = 1;
            }
            if ($data === false) {
                Output::info($this->register_client->swoole_client->errCode, 'ping32');
                if ($this->register_client->swoole_client->errCode == 32) {
                    $this->register_client->connect();
                }
            }
        } else {
            $this->register_client->connect();
        }


        \Swoole\Coroutine\System::sleep(4);
        $this->ping();

    }
}