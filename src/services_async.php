<?php

use Arachne\Client\Guzzle;
use Arachne\Engine\Async;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

require __DIR__ . '/services.php';

$container['scraper'] = function ($c) {
    $logger = $c['logger'];
    $client = new Guzzle($c['httpClient']);
    $identityRotator = $c['identityRotator'];
    $scheduler = $c['scheduler'];
    $docManager = $c['documentManager'];
    $requestFactory = $c['requestFactory'];
    $eventDispatcher = $c['eventDispatcher'];
    return new Async($logger, $client, $identityRotator, $scheduler, $docManager, $requestFactory, $eventDispatcher);
};

$container['httpClient'] = function ($c) {
    $logger = $c['logger'];
    $stack = HandlerStack::create(new CurlMultiHandler(
        ['handle_factory' => new CurlFactory(0)]
    ));
    $stack->push(Middleware::retry($c['createRetryHandler']($logger), $c['createDelayHandler']($logger)));

    $client = new Client([
        'handler' => $stack, //HandlerStack::create(),
        'connect_timeout' => $c['CONNECT_TIMEOUT'],
        'timeout' => $c['TIMEOUT'],
        'http_errors' => false,
        'verify' => false,
        'allow_redirects' => [
            'max' => $c['MAX_REDIRECTS'],
            'protocols' => ['http', 'https'],
            'strict' => false,
            'track_redirects' => true,
        ],
    ]);
    return $client;
};
