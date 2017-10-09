<?php

require 'vendor/autoload.php';

$service = \Arachne\Service\Container::create()->get();

$request = $service->requestFactory()
    ->createRequest('GET', 'http://httpbin.org/get');

echo $service->client()
    ->sendRequest($request)
    ->getBody();
