<?php
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Psr\Http\Message\ResponseInterface;

require 'vendor/autoload.php';
require 'src/services.php';

$container['identities'] = function ($c) {
    $gatewayServers = [
        GatewayServer::fromString('35.237.45.209:80'),
        GatewayServer::fromString('136.228.128.164:37217'),
//                GatewayServer::fromString('217.219.20.136:8080'),
//                GatewayServer::fromString('178.128.115.98:8080'),
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
