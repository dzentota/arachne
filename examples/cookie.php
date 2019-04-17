<?php
require 'vendor/autoload.php';
require 'src/services.php';

use Arachne\ResultSet;

$container['scraper']->addHandlers(
    ['success' => function(string $response, ResultSet $resultSet){
        echo $response;
    }]
)->scrapeUrls('http://httpbin.org/cookies/set?k2=v2&k1=v1');
