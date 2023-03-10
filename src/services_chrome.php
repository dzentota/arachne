<?php

use Arachne\Client\Chrome;
use HeadlessChromium\BrowserFactory;

require __DIR__ . '/services.php';

$container['client'] = function ($c) {
    return new Chrome(new BrowserFactory());
};
