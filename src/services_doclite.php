<?php

use Arachne\Frontier\FrontierLogger;

use Arachne\Document\DocumentLogger;
use Arachne\Document\DocLite as DocLiteStorage;
use Arachne\Filter\FilterLogger;
use Arachne\Filter\DocLite as DocLiteFilter;
use Arachne\Frontier\DocLite as DocLiteFrontier;
use Gebler\Doclite\FileDatabase;

require __DIR__ . '/services.php';
/**
 * @var array $container
 */

$container['DOCLITE_DB'] = $container['storage_dir'] . '/scraper.db';

$container['frontier'] = function ($c) {
    $logger = $c['logger'];
    $database = $c['docLiteDb'];
    return new FrontierLogger(new DocLiteFrontier($database), $logger);
};

$container['documentStorage'] = function ($c) {
    $logger = $c['logger'];
    $database = $c['docLiteDb'];
    return new DocumentLogger(new DocLiteStorage($database), $logger);
};

$container['filter'] = function ($c) {
    $logger = $c['logger'];
    $database = $c['docLiteDb'];
    return new FilterLogger(new DocLiteFilter($database), $logger);
};

$container['docLiteDb'] = function ($c) {
//    return new MemoryDatabase();
    return new FileDatabase($c['DOCLITE_DB']);
};
