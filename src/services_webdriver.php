<?php

use Arachne\Client\Browser;
use Arachne\Engine\Parallel;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Symfony\Component\Panther\Client;

require __DIR__ . '/services.php';

$container['scraper'] = function ($c) {
    $logger = $c['logger'];
    $client = new Browser($c['httpClient']);
    $identityRotator = $c['identityRotator'];
    $scheduler = $c['scheduler'];
    $docManager = $c['documentManager'];
    $requestFactory = $c['requestFactory'];
    $eventDispatcher = $c['eventDispatcher'];
    return new Parallel($logger, $client, $identityRotator, $scheduler, $docManager, $requestFactory, $eventDispatcher);
};

$container['httpClient'] = function ($c) {
    return Client::createChromeClient('/usr/local/bin/chromedriver', ['--no-sandbox','--headless'], ['port'=>4444]);
//    $host = 'http://localhost:4444/';
//    $chromeOptions = new ChromeOptions();
//    $chromeOptions->addArguments(['--no-sandbox', '--headless']);
//    $desiredCapabilities = DesiredCapabilities::chrome();
//    $desiredCapabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);
//    return RemoteWebDriver::create($host, $desiredCapabilities);
};

