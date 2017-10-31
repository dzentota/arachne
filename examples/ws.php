<?php
require('vendor/autoload.php');

use ChromeDevTools\Chrome;

$chrome = new Chrome();

$chrome->Page->enable();
$chrome->Network->enable();
$chrome->DOM->enable();
$chrome->Page->navigate(['url' => 'https://github.com']);
$responseReceivedData = $chrome->waitEvent('Network.responseReceived', 5);
$response = $responseReceivedData['matching_message']['params']['response'];
print_r($response['status']);
print_r($response['headers']);
$events = $chrome->waitEvent("Page.loadEventFired", 5);
$chrome->DOM->getDocument();
$result = $chrome->DOM->getOuterHTML(['nodeId' => 1]);
$html = $result['result']['outerHTML'];
echo $html;