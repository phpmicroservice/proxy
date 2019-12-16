<?php

namespace proxy;

use Phalcon\Events\Event;
use pms\Output;
use spms\Data;
use spms\Data2;

/**
 * 链接工具
 * Class Proxy
 * @property \pms\bear\ClientCoroutine $proxy_client
 * @package pms
 */
class Proxy
{
    private $proxy_addr;
    private $proxy_port;
    private $proxy_key;
    private $proxy_client;
    private $auth_status = false;


    /**
     * 配置初始化
     */
    public function __construct($proxy_addr,$proxy_port,$proxy_key)
    {
        
        $this->proxy_addr = $proxy_addr;
        $this->proxy_port =$proxy_port;
        $this->proxy_key =$proxy_key;

        \pms\output([$this->proxy_addr, $this->proxy_port], 'Proxy');
        $this->proxy_client = new \pms\bear\ClientCoroutine(
            $this->proxy_addr,
            $this->proxy_port,
            30);
        $this->auth();
    }

    public function auth()
    {
        $config = \Phalcon\Di\FactoryDefault\Cli::getDefault()->getShared('config');
        $data = new Data2();
        $time = time().uniqid();

        $this->ask_recv('proxy','/auth',[
            'key'=>md5($this->proxy_key.$time),
            'time'=>$time
        ]);
        $data = $this->proxy_client->recv();
        \pms\output([$this->proxy_key,$data], 'auth');
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


    public function ask_recv($server, $router, $data)
    {
        return $this->send_recv([
            's' => $server,
            'r' => $router,
            'd' => $data
        ]);
    }

    /**
     * 发送并接受返回
     * @param $data
     */
    public function send_recv($data)
    {

        $re = $this->proxy_client->send($data);
        if (!$re) {
            return $re;
        }
        return $this->proxy_client->recv();
    }



    /**
     * 发送一个请求
     * @param $router
     * @param $data
     * @return bool
     */
    public function send_ask($server, $router, $data)
    {
        return $this->proxy_client->send([
            's' => $server,
            'r' => $router,
            'd' => $data
        ]);
    }





}