<?php
namespace Arachne\Service;

class Proxy
{
    private $serviceFactory;
    private $pool;

    public function __construct(Factory $serviceFactory)
    {
        $this->serviceFactory = $serviceFactory;
        $this->serviceFactory->setContainer($this);
        $this->pool = [];
    }

    public function __call($method, $arguments)
    {
        if (!isset($this->pool[$method])) {
            $this->pool[$method] = call_user_func_array([$this->serviceFactory, $method], $arguments);//$this->serviceFactory->$method();
        }
        return $this->pool[$method];
    }

    public function register($method, $result)
    {
        $this->pool[$method] = $result;
    }
}