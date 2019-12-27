<?php

require 'vendor/autoload.php';
require 'src/services.php';

$request = $container['requestFactory']
    ->createRequest('GET', 'https://httpbin.org/ip');
try {
    echo $container['httpClient']
        ->send($request, ['proxy' => '82.117.215.14:8088'])
        ->getBody();
} catch (\Exception $exception) {
    $container['logger']->critical('Something went wrong :-(');
}
