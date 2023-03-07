<?php

use Arachne\Client\Guzzle;
use Arachne\Engine\Parallel;

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
