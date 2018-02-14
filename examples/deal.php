<?php
use Arachne\Crawler\DomCrawler;
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\Identity;
use Arachne\ResultSet;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\ResponseInterface;
use Arachne\Identity\IdentitiesCollection;

require 'vendor/autoload.php';

$service = \Arachne\Service\Container::create(new \Arachne\Service\Proxy(
    new class extends \Arachne\Service\MongoFactory
    {
        protected function getDbName()
        {
            return 'deal';
        }

        protected function createHttpClient()
        {
            $logger = $this->getContainer()->logger();
            $stack = HandlerStack::create(new CurlHandler());
            $stack->push(Middleware::retry($this->createRetryHandler($logger), $this->createDelayHandler($logger)));

            $client = new Client([
                'handler' => $stack,
                'connect_timeout' => 15,
                'timeout' => 15,
                'allow_redirects' => [
                    'max' => 5,
                    'protocols' => ['http', 'https'],
                    'strict' => false,
                    'track_redirects' => true,
                ],
                'http_errors' => false,
            ]);
            return $client;
        }

        public function identities(): IdentitiesCollection
        {

            $gatewayServer = GatewayServer::fromString('91.216.93.126:777');
            $gateway = new Gateway($this->getContainer()->eventDispatcher(), $gatewayServer, $this->gatewayProfile());
            $gateway->setMaxConsecutiveFails(1000);
            $defaultUserAgent = \Campo\UserAgent::random();
            $identity = new Identity($gateway, $defaultUserAgent);
            return new IdentitiesCollection($identity);
        }
    }
))->get();

$frontier = [];
$baseUrl = 'https://deal.by/cs/';
for ($i = 1; $i < 2; $i++) {
    $frontier[] =
        ['url' => $baseUrl . $i, 'type' => 'page'];
}
$h = fopen('deal.csv', 'a+');
$service->scraper()
    ->scrape([
        'frontier' => $frontier,
        'success:page' => function (ResponseInterface $response, ResultSet $resultSet) use ($h){
            $title = (new DomCrawler((string)$response->getBody()))->filter('title')->text();
            $redirects = $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER);
            $origUrl = $resultSet->getResource()->getUrl();
            $effectiveUrl = empty($redirects)? $origUrl : array_pop($redirects);
            $data = [$origUrl, $title, $effectiveUrl];
            fputcsv($h, $data);
        },
        'fail:page' => function (ResponseInterface $response = null, \Arachne\Resource $resource = null, \Exception $exception = null) use($h, $service){
            $status = 'Error ';
            if ($response) {
                if (429 === $response->getStatusCode()) {
                    die('Banned');
                }
                $status .= $response->getStatusCode();
            } else {
                $service->logger()->warning('RESCHEDULING RESOURCE');
                if (!empty($resource)) {
                    $service->frontier()->populate($resource);
                } else {
                    $service->logger()->error('NO RESOURCE TO RESCHEDULE');
                }

            }
            $redirects = $response? $response->getHeader(\GuzzleHttp\RedirectMiddleware::HISTORY_HEADER) : [];
            $origUrl = $resource? $resource->getUrl() : '';
            $effectiveUrl = empty($redirects)? $origUrl : array_pop($redirects);
            $data = [$origUrl, $status, $effectiveUrl];
            fputcsv($h, $data);
        },

    ]);
