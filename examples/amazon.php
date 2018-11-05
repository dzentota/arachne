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
use Psr\Log\LoggerInterface;
use Respect\Validation\Validator as v;
use Arachne\Identity\IdentitiesCollection;

$container = \Arachne\Service\Container::create();

$container = \Arachne\Service\Container::create(new \Arachne\Service\Proxy(
    new class extends \Arachne\Service\MongoFactory
    {
        protected function getDbName()
        {
            return 'amazon';
        }

        protected function createDelayHandler(LoggerInterface $logger)
        {
            return function ($retries) use ($logger) {
                $delay = 1000 * (int)pow(2, $retries - 1);
                $logger->debug("Sleeping $delay milliseconds before retry");
                return $delay;
            };
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
//                GatewayServer::fromString('213.159.247.209:3128'),
//                GatewayServer::fromString('177.180.153.65:55808'),
//                GatewayServer::fromString('128.127.163.223:32231'),
//                GatewayServer::fromString('173.249.36.171:3128'),
//                GatewayServer::fromString('177.105.232.105:8080'),
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
    protected $price;

    protected $type = 'product';

    public function getValidator()
    {
        return v::attribute('title', v::stringType())
            ->attribute('url', v::stringType())
            ->attribute('price', v::stringType())
//            ->attribute('mapped', v::arrayVal()->each(v::stringType()))
            ;

    }

//    public function normalizeUrl()
//    {
//        $purl = new \Purl\Url($this->url);
//        $purl->query->remove('utm_source');
//        $purl->query->remove('utm_medium');
//        $purl->query->remove('utm_campaign');
//        $this->url = $purl->getUrl();
//    }
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
            ['url' => 'https://www.amazon.com/s/s/ref=sr_nr_p_n_availability_1?rh=n%3A2619533011%2Cn%3A%212619534011%2Cn%3A2975312011%2Cp_72%3A2661618011%2Cp_n_availability%3A2661601011&bbn=2975312011&ie=UTF8&qid=1541426639', 'type' => 'category'],
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
            $crawler->filter('#atfResults .s-item-container')->each(function (DomCrawler $crawler) use ($resultSet){
                $prime = $crawler->filter('.a-icon-prime')->attr('class');
//                if (!empty($prime)) {
                    $link = $crawler->filter('a.s-access-detail-page')->attr('href');
                    $resultSet->addHighPriorityResource('item', $link);
//                }
            });
            $crawler->filter('.pagnLink a')->each(function (DomCrawler $crawler) use ($resultSet) {
                $link = 'https://www.amazon.com' . $crawler->attr('href');
                $resultSet->addResource('category', $link);
            });
        },

        'success:item' => function (ResponseInterface $response, ResultSet $resultSet) {
            $content = (string)$response->getBody();
            $crawler = new DomCrawler($content);
            $data['title'] =  trim($crawler->filter('#title')->text());
            $data['url'] = $resultSet->getResource()->getUrl();
            var_dump($crawler->filter('#priceblock_ourprice')->text());
            $data['price'] = trim($crawler->filter('#priceblock_ourprice')->text());

            $item = new Product($data);
      //      $item->normalizeUrl();
            $resultSet->addItem($item);
//            print_r($item);
//            die();
        },
    ], \Arachne\Mode::RESUME)
//    ->dumpDocuments('product')
;