<?php

require 'vendor/autoload.php';
require 'src/services.php';

$request = $container['requestFactory']
    ->createRequest('GET', 'https://httpbin.org/redirect-to?url=http%3A%2F%2Fgoogle.com');

$history =  $container['client']
    ->sendRequest($request)
    ->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);

var_dump($history);
