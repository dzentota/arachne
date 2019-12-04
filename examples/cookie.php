<?php
require 'vendor/autoload.php';
require 'src/services.php';

use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;

$container['scraper']->addHandlers(
    ['success' => function(ResponseInterface $response, ResultSet $resultSet){
        echo $response->getBody()->getContents();
    }]
)->scrapeUrls('http://httpbin.org/cookies/set?k2=v2&k1=v1');
