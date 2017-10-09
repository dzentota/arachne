<?php
require 'vendor/autoload.php';

use Psr\Http\Message\ResponseInterface;
use Arachne\ResultSet;

$container = \Arachne\Service\Container::create();

$httpRequest = $container->get()->requestFactory()
    ->createRequest('GET', 'http://httpbin.org/cookies/set?k2=v2&k1=v1');
$resource = new \Arachne\Resource($httpRequest, 'cookie');

$container->get()->scraper()
    ->scrape([
        'frontier'=> [['resource'=>$resource]],
        'success:cookie' => function(ResponseInterface $response, ResultSet $resultSet){
            echo $response->getBody();
        },

    ]);