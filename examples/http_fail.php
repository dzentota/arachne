<?php
require 'vendor/autoload.php';
require 'src/services.php';

use Psr\Http\Message\ResponseInterface;

$container['scraper']->addHandlers(
    ['fail' => function(ResponseInterface $response, \Arachne\HttpResource $resource, \Exception $exception = null){
        echo 'Failed to load ' . $resource->getUrl(), PHP_EOL;
        echo 'Response headers:' . var_export($response->getHeaders(), true);
    }]
)->scrapeUrls('http://httpbin.org/status/503');
