<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

require 'vendor/autoload.php';

$client = new Client(['proxy' => '89.163.212.9:45263',
    'connect_timeout' => 15,
    'timeout' => 15,
    'allow_redirects' => [
        'max' => 5,
        'protocols' => ['http', 'https'],
        'strict' => false,
        'track_redirects' => true,
    ],
    'http_errors' => false,
    ]);

$h = fopen('deal2.csv', 'a+');

$requests = function ($total) {
    $uri = 'https://deal.by/cs/';
    for ($i = 0; $i < $total; $i++) {
        yield new Request('GET', $uri . (40000 + $i));
    }
};

$pool = new GuzzleHttp\Pool($client, $requests(5), [
    'concurrency' => 10,
    'fulfilled' => function ($response, $index) use ($h){
        $content = (string)$response->getBody();
        preg_match('~<title>(.*?)</title>~is', $content, $m);
        $redirects = $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
        $origUrl = 'https://deal.by/cs/' . (40000 + $index);
        $effectiveUrl = empty($redirects)? $origUrl : array_pop($redirects);
        $data = [$origUrl, $m[1], $effectiveUrl];
        fputcsv($h, $data);
    },
    'rejected' => function ($reason, $index) use ($h) {
        $origUrl = 'https://deal.by/cs/' . (40000 + $index);
        $data = [$origUrl, $reason, $origUrl];
        fputcsv($h, $data);
    },
]);

// Initiate the transfers and create a promise
$promise = $pool->promise();

// Force the pool of requests to complete.
$promise->wait();
