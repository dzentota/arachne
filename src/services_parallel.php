<?php

use Arachne\Client\Guzzle;
use Arachne\Engine\Parallel;
use Arachne\Event\Event;
use Arachne\Event\EventSummaryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

require __DIR__ . '/services.php';

$container['scraper'] = function ($c) {
    $logger = $c['logger'];
    $client = new Guzzle($c['httpClient']);
    $identityRotator = $c['identityRotator'];
    $scheduler = $c['scheduler'];
    $docManager = $c['documentManager'];
    $requestFactory = $c['requestFactory'];
    $eventDispatcher = $c['eventDispatcher'];
    return new Parallel($logger, $client, $identityRotator, $scheduler, $docManager, $requestFactory, $eventDispatcher);
};
