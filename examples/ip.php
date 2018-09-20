<?php
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Psr\Http\Message\ResponseInterface;

require 'vendor/autoload.php';

$container = \Arachne\Service\Container::create(new \Arachne\Service\Proxy(
    new class extends \Arachne\Service\GenericFactory
    {
        function isAlive()
        {
            return function (\Arachne\Gateway\GatewayInterface $gateway, ResponseInterface $response) {
                if (false !== strpos($response->getBody(), 'Internal Privoxy Error') ){
                    echo sprintf('Proxy %s is blocked', $gateway->getGatewayServer()), PHP_EOL;
                    $gateway->block();
                    return false;
                }
                return true;
            };
        }

        public function identities(): IdentitiesCollection
        {
//            // use curl to make the request
//            $url = 'http://falcon.proxyrotator.com:51337/?apiKey=ZmNgTMuSW6edUFYKEGtJDAhQHfcojCPR';
//            $ch = curl_init($url);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
//            $response = curl_exec($ch);
//            curl_close($ch);
//
//// decode the json response
//            $json = json_decode($response, true);
//
//// create $proxy to contain the ip:port ready to use
//            $proxy = $json['proxy'];
            $gatewayServers = [
//                GatewayServer::localhost(),
//                GatewayServer::fromString($proxy),
                GatewayServer::fromString('213.159.247.209:3128'),
//                GatewayServer::fromString('https://95.181.0.66:3128'),
//                GatewayServer::fromString('https://198.168.140.84:3128'),//will be skipped by GatewayProfile
            ];
            $identities = [];
            foreach ($gatewayServers as $i => $gatewayServer) {
                $gateway = new Gateway($this->getContainer()->eventDispatcher(), $gatewayServer,
                    $i < 2? $this->gatewayProfile() : new GatewayProfile(null, [
                        'httpbin\.org'
                    ])
                );
                $defaultUserAgent = \Campo\UserAgent::random();
                $identity = new Identity($gateway, $defaultUserAgent);
                $identities[] = $identity;
            }
            return new IdentitiesCollection(...$identities);
        }

    }
));
$service = $container->get();

for ($i = 0; $i < 5; $i++) {
    $request = $container->get()->requestFactory()
        ->createRequest('GET', 'https://httpbin.org/ip');
    try {
        echo $service->client()
            ->sendRequest($request)
            ->getBody();
    } catch (\Exception $exception) {
        $container->get()->logger()->critical('Something went wrong :-(');
    }
}
