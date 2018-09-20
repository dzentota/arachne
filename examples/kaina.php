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

        public function identities(): IdentitiesCollection
        {
            $gatewayServers = [
//                GatewayServer::localhost(),
//                GatewayServer::fromString('213.159.247.209:3128'),
//                GatewayServer::fromString('177.180.153.65:55808'),
//                GatewayServer::fromString('128.127.163.223:32231'),
//                GatewayServer::fromString('173.249.36.171:3128'),
                GatewayServer::fromString('177.105.232.105:8080'),
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


class Product extends \Arachne\Item
{
    protected $title;
    protected $url;
    protected $mapped;

    protected $type = 'product';

    public function getValidator()
    {
        return v::attribute('title', v::stringType())
            ->attribute('url', v::stringType())
            ->attribute('mapped', v::arrayVal()->each(v::stringType()))
            ;

    }

    public function normalizeUrl()
    {
        $purl = new \Purl\Url($this->url);
        $purl->query->remove('utm_source');
        $purl->query->remove('utm_medium');
        $purl->query->remove('utm_campaign');
        $this->url = $purl->getUrl();
    }
}

$container->get()->eventDispatcher()
    ->addListener(ResponseReceived::name, function(Event $event) use ($container) {
        $container->get()->logger()->warning('SLEEPING...');
        sleep(1);

    });

$container->get()
    ->scraper()
    ->scrape([
        'frontier' => [
            ['url' => 'https://www.kaina24.lt/top/', 'type' => 'top'],
        ],
        'success:top' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
            $content = (string)$response->getBody();
            $crawler = new DomCrawler($content);
            $crawler->filter('.list-block .fr a')->each(function (DomCrawler $crawler) use ($resultSet){
                $resultSet->addResource('category', $crawler->attr('href'));
            });
        },
        'success:category' => function (ResponseInterface $response, ResultSet $resultSet) use ($container) {
            $content = (string)$response->getBody();
            $crawler = new DomCrawler($content);
            $crawler->filter('.product-item .details-1 h2 a')->each(function (DomCrawler $crawler) use ($resultSet){
                $resultSet->addHighPriorityResource('item', $crawler->attr('href'));
            });
        },

        'success:item' => function (ResponseInterface $response, ResultSet $resultSet) {
            $content = (string)$response->getBody();
            $crawler = new DomCrawler($content);
            $data['title'] = str_replace(' kaina', '', $crawler->filter('h1')->text());
            $data['url'] = $resultSet->getResource()->getUrl();
            $mapped = [];
            $crawler->filter('.seller-item-table h3 a')->each(function(DomCrawler $crawler) use (&$mapped) {
                $onclick = $crawler->attr('onclick');
                preg_match("~'eventAction':\s*'(.*?)'~is", $onclick, $m);
                if (!empty($m[1])) {
                    $mapped[] = $m[1];
                }
            });
            $data['mapped'] = $mapped;
            $item = new Product($data);
            $item->normalizeUrl();
            $resultSet->addItem($item);
//            print_r($item);
//            die();
        },
    ], \Arachne\Mode::RESUME)
//    ->dumpDocuments('product')
;