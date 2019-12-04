<?php
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;

require 'vendor/autoload.php';
require 'src/services.php';

$container['identities'] = function ($c) {
    $gatewayServers = [
        GatewayServer::fromString('35.237.45.209:80'),
        GatewayServer::fromString('136.228.128.164:37217'),
    ];
    $identities = [];
    foreach ($gatewayServers as $i => $gatewayServer) {
        $gateway = new Gateway($c['eventDispatcher'], $gatewayServer,
            $c['gatewayProfile']
        );
        $defaultUserAgent = \Campo\UserAgent::random();
        $identity = new Identity($gateway, $defaultUserAgent);
        $identities[] = $identity;
    }
    return new IdentitiesCollection(...$identities);
};

for ($i = 0; $i < 5; $i++) {
    $request = $container['requestFactory']
        ->createRequest('GET', 'https://httpbin.org/ip');
    try {
        echo $container['client']
            ->sendRequest($request)
            ->getBody();
    } catch (\Exception $exception) {
        $container['logger']->critical('Something went wrong :-(');
    }
}
