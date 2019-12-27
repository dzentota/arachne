<?php
require 'vendor/autoload.php';
require 'src/services.php';

use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;

$start = microtime(true);
$container['scraper']->addHandlers(
    ['success' => function(ResponseInterface $response, ResultSet $resultSet) use ($start){
        echo "Passed: " .  (microtime(true) - $start), PHP_EOL;
    }]
)->scrapeUrls('http://httpbin.org/delay/3', 'http://httpbin.org/delay/3', 'http://httpbin.org/delay/3');

