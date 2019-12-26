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
        if($this->is_cli()){
            $this->proxy_client = new \pms\bear\ClientCoroutine(
                $this->proxy_addr,
                $this->proxy_port,
                30);
        }else{
            $this->proxy_client = new \pms\bear\ClientSync(
                $this->proxy_addr,
                $this->proxy_port,
                10);
        }


        $this->auth();
    }

    function is_cli()
    {
        return preg_match("/cli/i", php_sapi_name()) ? true : false;
    }

    public function auth()
    {
        $config = \Phalcon\Di\FactoryDefault\Cli::getDefault()->getShared('config');
        $data = new Data2();
        $time = time().uniqid();

        $data63 = $this->ask_recv('proxy','/auth',[
            'key'=>md5($this->proxy_key.$time),
            'time'=>$time
        ]);
        \pms\output([$this->proxy_key,$data63], 'auth');
    }

    

    /**
     * 请求并等待返回
     * @param $server
     * @param $router
     * @param $data
     * @return array|mixed
     */
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
        $re = $this->send($data);
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
        return $this->send([
            's' => $server,
            'r' => $router,
            'd' => $data
        ]);
    }
    
    /**
     * 
     * 发送消息
     * @param type $data
     * @return type
     */
    private function send($data)
    {
        $data =$this->proxy_client->send($data);
        
        if ($data === false) {
            Output::error($this->proxy_client->swoole_client->errCode, 'proxy-send');
            if ($this->proxy_client->swoole_client->errCode == 32) {
                $this->proxy_client->connect();
            }
        }
        return $data;
    }





}