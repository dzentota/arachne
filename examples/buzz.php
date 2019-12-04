<?php
require 'vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;

class MyClient extends \Buzz\Client\Curl
{
    protected function createHandle()
    {
        return curl_init();
    }
}

$client = new MyClient(new Psr17Factory());
$response = $client->sendRequest(new \Zend\Diactoros\Request('https://httpbin.org/ip', 'GET'),
    ['proxy' => '110.77.183.189:8080']
);

echo $response->getBody();

//$client = new \Buzz\Client\Curl(new Psr17Factory());
$response2 = $client->sendRequest(new \Zend\Diactoros\Request('https://httpbin.org/ip', 'GET'),
    ['proxy' => '45.119.83.146:19238']
);

echo $response2->getBody();