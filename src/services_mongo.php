<?php

use Arachne\Frontier\FrontierLogger;

use Arachne\Document\DocumentLogger;
use Arachne\Document\Mongo as MongoStorage;
use Arachne\Filter\FilterLogger;
use Arachne\Filter\Mongo as MongoFilter;
use Arachne\Frontier\Mongo as MongoFrontier;

require __DIR__ . '/services.php';

$container['MONGO_DB_NAME'] = 'scraper';

$container['frontier'] = function ($c) {
    $logger = $c['logger'];
    $client = $c['mongoDbClient'];
    return new FrontierLogger( new MongoFrontier($client, $c['MONGO_DB_NAME']), $logger);
};

$container['documentStorage'] = function ($c) {
    $logger = $c['logger'];
    $client = $c['mongoDbClient'];
    return new DocumentLogger(new MongoStorage($client, $c['MONGO_DB_NAME']), $logger);
};

$container['filter'] = function ($c) {
    $logger = $c['logger'];
    $client = $c['mongoDbClient'];
    return new FilterLogger(new MongoFilter($client, $c['MONGO_DB_NAME']), $logger);
};

$container['mongoDbClient'] = function ($c) {
    $client = new \MongoDB\Client('mongodb://mongo', ['username' => 'root', 'password' => 'root']);
    return $client;
};
