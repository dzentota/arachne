<?php

require 'vendor/autoload.php';
require 'src/services.php';

$request = $container['requestFactory']
    ->createRequest('GET', 'http://httpbin.org/get');

echo $container['httpClient']
    ->send($request)
    ->getBody()
    ->getContents();
