<?php
require 'vendor/autoload.php';

use Arachne\Client\Events\ResponseReceived;
use Arachne\Event\Event;
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\Identity;
use Psr\Http\Message\ResponseInterface;
use Arachne\Crawler\DomCrawler;
use Arachne\ResultSet;
use Respect\Validation\Validator as v;
use Arachne\Identity\IdentitiesCollection;

$container = \Arachne\Service\Container::create();

$container = \Arachne\Service\Container::create(new \Arachne\Service\Proxy(
    new class extends \Arachne\Service\MongoFactory
    {
        protected function getDbName()
        {
            return 'kaina';
        }

        protected function getMongoDbClient(): \MongoDB\Client
        {
            $client = new \MongoDB\Client('mongodb://root:root@mongo');
            return $client;
        }

        public function identities(): IdentitiesCollection
        {
            $gatewayServers = [
                GatewayServer::localhost(),
//                GatewayServer::fromString('https://202.178.123.124:30231'),
//                GatewayServer::fromString('https://182.253.123.9:3128'),
//                GatewayServer::fromString('https://217.219.20.136:8080'),
//                GatewayServer::fromString('https://178.128.115.98:8080'),
            ];
            $identities = [];
            foreach ($gatewayServers as $i => $gatewayServer) {
                $gateway = new Gateway($this->getContainer()->eventDispatcher(), $gatewayServer,
                    $this->gatewayProfile());
                $defaultUserAgent = \Campo\UserAgent::random();
                $identity = new Identity($gateway, $defaultUserAgent);
                $identities[] = $identity;
            }
            return new IdentitiesCollection(...$identities);
        }
    }
));

$container->get()->eventDispatcher()
    ->addListener(ResponseReceived::name, function(Event $event) use ($container) {
        $container->get()->logger()->warning('SLEEPING...');
        usleep(200000);

    });
$container->get()->eventDispatcher()
    ->addListener(\Arachne\Gateway\Events\GatewayBlocked::name, function(Event $event) use ($container) {
        $container->get()->logger()->critical($event->getSummary());

    });


$container->get()
    ->scraper()
    ->scrape([
        'frontier' => [
            ['url' => 'https://www.kainos.lt/mobilieji-telefonai', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/smulki-buitine-technika', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/saldytuvai', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/sildymo-vedinimo-iranga', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/virykles', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/saldikliai', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/skalbimo-masinos', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/kaitlentes', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/smulki-namu-technika', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/gartraukiai', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/orkaites', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/indaploves', 'type' => 'category'],
            ['url' => 'https://www.kainos.lt/dziovykles', 'type' => 'category'],
        ],
        'success:category' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
            $crawler = new DomCrawler((string)$response->getBody());
        $crawler->filter('.pages_ul_inner li a')->each(function (DomCrawler $crawler) use ($resultSet) {
            $nextPage = 'https://www.kainos.lt' . $crawler->attr('href');
            $resultSet->addResource('category', $nextPage);
        });
        $crawler->filter('.category-listing .main-link')->each(function (DomCrawler $crawler) use ($resultSet) {
            $productUrl = 'https://www.kainos.lt' . $crawler->attr('href');
            if (false !== strpos($productUrl, 'redirect-to-shop')) {
                return;
            }
            $resultSet->addHighPriorityResource('item', $productUrl);
        });
        },

        'success:item' => function (ResponseInterface $response, ResultSet $resultSet) {
            $url = $resultSet->getResource()->getUrl();
        if (false !== strpos($url, 'redirect-to-shop')) {
            return;
        }
        $data = parse_url($url);
        list(,$category,$title) = explode('/', $data['path']);
        $targetDir = __DIR__ . '/kainos/' . $category;
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        file_put_contents($targetDir . '/' .$title . '.html', (string) $response->getBody());
        },
    ], \Arachne\Mode::RESUME);
