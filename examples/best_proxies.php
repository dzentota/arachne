<?php

use Arachne\Gateway\Events\GatewayFailed;
use Arachne\Gateway\Events\GatewaySucceeded;
use Arachne\Gateway\Gateway;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Arachne\ResultSet;
use Psr\Http\Message\ResponseInterface;

require 'vendor/autoload.php';
require 'src/services.php';

//$container['LOGGER_LEVEL'] = \Monolog\Logger::ERROR;
//$container['TIMEOUT'] = 15;
//Elite and anonymous HTTPS and Socks5 proxies with response time not greater than 1000ms
$data = file('https://api.best-proxies.ru/proxylist.txt?key=1a4cbf910eca75015a92be62fdb8319b&limit=20&level=1,2&&response=1000&type=socks5,https');

$container['identities'] = function ($c) use ($data) {
    $identities = [];
    foreach ($data as $p) {
        $gatewayServer = \Arachne\Gateway\GatewayServer::fromString('https://' . trim($p));
        $gateway = new Gateway($c['eventDispatcher'], $gatewayServer,
            $c['gatewayProfile']
        );
        $defaultUserAgent = \Campo\UserAgent::random();
        $identity = new Identity($gateway, $defaultUserAgent);
        $identities[] = $identity;
    }
    return new IdentitiesCollection(...$identities);
};

$resources = [];
for ($i = 0; $i < 20; $i++) {
    $resources[] = \Arachne\HttpResource::fromUrl('https://httpbin.org/ip', 'ip');
}
$dispatcher = $container['eventDispatcher'];
$dispatcher->addListener(GatewaySucceeded::name, function (GatewaySucceeded $event) {
    echo "Gateway succeeded: " . $event->getGatewayServer(), PHP_EOL;
});

$dispatcher->addListener(GatewayFailed::name, function (GatewayFailed $event) {
    echo "Gateway failed: " . $event->getGatewayServer(), PHP_EOL;
});
/**
 * @var Arachne\Engine $scraper
 */
$scraper = $container['scraper'];
$scraper
    ->prepareEnv(\Arachne\Mode::CLEAR)
    ->setConcurrency(20)
    ->addHandlers(
        [
            'success:ip' => function (ResponseInterface $response, ResultSet $resultSet) {
                echo 'SUCCESS:' . $response->getBody()->getContents(), PHP_EOL;
            }
        ])
    ->scrape(...$resources);
