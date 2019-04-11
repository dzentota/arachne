<?php

namespace Arachne\Service;

use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\IdentityRotatorInterface;
use Arachne\Identity\RoundRobinIdentityRotator;
use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Http\Message\MessageFactory\DiactorosMessageFactory;
use Http\Message\RequestFactory;
use Http\Message\ResponseFactory;
use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Arachne\BlobsStorage\Gaufrette;
use Arachne\Client\ClientInterface;
use Arachne\Client\ClientLogger;
use Arachne\Client\GuzzleClient;
use Arachne\Document\DocumentInterface;
use Arachne\Document\DocumentLogger;
use Arachne\Document\InMemory as InMemoryStorage;
use Arachne\Document\Manager;
use Arachne\Event\Event;
use Arachne\Event\EventSummaryInterface;
use Arachne\Filter\FilterInterface;
use Arachne\Filter\FilterLogger;
use Arachne\Filter\InMemory as InMemoryFilter;
use Arachne\Frontier\FrontierInterface;
use Arachne\Frontier\FrontierLogger;
use Arachne\Frontier\InMemory as InMemoryFrontier;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Arachne\Scheduler;
use Arachne\Arachne;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zoya\Monolog\Formatter\ColoredConsoleFormatter;

class GenericFactory extends Factory
{
    const MAX_RETRIES = 2;

    /**
     * @return FrontierInterface
     */
    public function frontier(): FrontierInterface
    {
        $logger = $this->getContainer()->logger();
        $frontier = new FrontierLogger(new InMemoryFrontier(new \SplPriorityQueue()), $logger);
        return $frontier;
    }

    public function gatewayProfile(): GatewayProfile
    {
        return new GatewayProfile();
    }

    public function identities(): IdentitiesCollection
    {
        $gatewayServer = GatewayServer::localhost();
        $gateway = new Gateway($this->getContainer()->eventDispatcher(), $gatewayServer, $this->gatewayProfile());
        $defaultUserAgent = \Campo\UserAgent::random();
        $identity = new Identity($gateway, $defaultUserAgent);
        return new IdentitiesCollection($identity);
    }

    /**
     * @return ClientInterface
     */
    public function client(): ClientInterface
    {
        $logger = $this->getContainer()->logger();
        $httpClient = $this->createHttpClient();
        $eventDispatcher = $this->getContainer()->eventDispatcher();
        $client = new GuzzleClient($eventDispatcher,
            $this->identityRotator(), $httpClient);
        return new ClientLogger($client, $logger);
    }

    public function identityRotator(): IdentityRotatorInterface
    {
        return new RoundRobinIdentityRotator($this->getContainer()->identities());
    }
    /**
     * @return DocumentInterface
     */
    public function documentStorage(): DocumentInterface
    {
        $logger = $this->getContainer()->logger();
        return new DocumentLogger(new InMemoryStorage(), $logger);
    }

    /**
     * @return FilterInterface
     */
    public function filter(): FilterInterface
    {
        $logger = $this->getContainer()->logger();
        return new FilterLogger(new InMemoryFilter(), $logger);
    }

    /**
     * @return Manager
     */
    public function documentManager(): Manager
    {
        $filesystem = $this->getContainer()->filesystem();
        $blobsStorage = new Gaufrette($this->logger(), $filesystem);
        return new Manager($this->getContainer()->documentStorage(), $blobsStorage);
    }


    public function logger(): LoggerInterface
    {
        $stream = new StreamHandler('php://stderr', \Monolog\Logger::DEBUG);
        $formatter = new ColoredConsoleFormatter(null, null, null, true);
        $stream->setFormatter($formatter);
        $logger = new \Monolog\Logger('Arachne');
        $logger->pushHandler($stream);
        return $logger;
    }

    public function requestFactory(): RequestFactory
    {
        return new DiactorosMessageFactory();
    }

    public function responseFactory(): ResponseFactory
    {
        return new DiactorosMessageFactory();
    }

    public function filesystem(): Filesystem
    {
        $filesystem = new Filesystem(new Local(sys_get_temp_dir()));
        return $filesystem;
    }

    public function scraper(): Arachne
    {
        $logger = $this->getContainer()->logger();
        $client = $this->getContainer()->client();
        $scheduler = $this->getContainer()->scheduler();
        $docManager = $this->getContainer()->documentManager();
        $requestFactory = $this->getContainer()->requestFactory();
        return new Arachne($logger, $client, $scheduler, $docManager, $requestFactory);
    }

    /**
     * @return Scheduler
     */
    public function scheduler(): Scheduler
    {
        return new Scheduler($this->getContainer()->frontier(), $this->getContainer()->filter(),
            $this->getContainer()->logger());
    }

    /**
     * @return Client
     */
    public function createHttpClient()
    {
        $logger = $this->getContainer()->logger();
        $stack = HandlerStack::create(new CurlHandler(['handle_factory' => new CurlFactory(0)]));
        $stack->push(Middleware::retry($this->createRetryHandler($logger), $this->createDelayHandler($logger)));

        $client = new Client([
            'handler' => $stack,
            'connect_timeout' => 5,
            'timeout' => 5,
            'http_errors' => false,
            'verify' => false,
            'allow_redirects' => [
                'max' => 5,
                'protocols' => ['http', 'https'],
                'strict' => false,
                'track_redirects' => true,
            ],
        ]);
        return $client;
    }

    protected function createRetryHandler(LoggerInterface $logger)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            RequestException $exception = null
        ) use ($logger) {
            if ($retries >= self::MAX_RETRIES) {
                $logger->error(sprintf(
                    'Max number [%s] of retries reached for %s %s / %s',
                    self::MAX_RETRIES,
                    $request->getMethod(),
                    $request->getUri(),
                    $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
                ));

                return false;
            }
            if (!($this->isServerError($response) || $this->isConnectError($exception))) {

                return false;
            }
            $logger->warning(sprintf(
                'Retrying %s %s %s/%s, %s',
                $request->getMethod(),
                $request->getUri(),
                $retries + 1,
                self::MAX_RETRIES,
                $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
            ), [$request->getHeader('Host')[0]]);
            return true;
        };
    }

    protected function createDelayHandler(LoggerInterface $logger)
    {
        return function ($retries) use ($logger) {
            $delay = 100 * (int)pow(2, $retries - 1);
            $logger->debug("Sleeping $delay milliseconds before retry");
            return $delay;
        };
    }

    /**
     * @param ResponseInterface $response
     * @return bool
     */
    protected function isServerError(ResponseInterface $response = null)
    {
        return $response && $response->getStatusCode() >= 500;
    }

    /**
     * @param RequestException $exception
     * @return bool
     */
    protected function isConnectError(RequestException $exception = null)
    {
        return $exception instanceof ConnectException;
    }

    public function eventDispatcher() : EventDispatcherInterface
    {
        $dispatcher = new WildcardEventDispatcher(new EventDispatcher());
        $logger = $this->getContainer()->logger();
        $dispatcher->addListener('#', function(Event $event) use ($logger) {
            if ($event instanceof EventSummaryInterface) {
                $logger->debug($event->getSummary());
            }
        });
        return $dispatcher;
    }

}
