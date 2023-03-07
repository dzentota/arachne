<?php

use Arachne\Client\Guzzle;
use Arachne\Engine\Basic;
use Arachne\Gateway\Gateway;
use Arachne\Gateway\GatewayProfile;
use Arachne\Gateway\GatewayServer;
use Arachne\Identity\RoundRobinIdentityRotator;
use Campo\UserAgent;
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
use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Arachne\BlobsStorage\Gaufrette;
use Arachne\Document\DocumentLogger;
use Arachne\Document\InMemory as InMemoryStorage;
use Arachne\Document\Manager;
use Arachne\Event\Event;
use Arachne\Event\EventSummaryInterface;
use Arachne\Filter\FilterLogger;
use Arachne\Filter\InMemory as InMemoryFilter;
use Arachne\Frontier\FrontierLogger;
use Arachne\Frontier\InMemory as InMemoryFrontier;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Identity\Identity;
use Arachne\Scheduler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pimple\Container;

$container = new Container();

$container['HTTP_MAX_RETRIES'] = 4;
$container['LOGGER_LEVEL'] = Level::Debug;
$container['CONNECT_TIMEOUT'] = 5;
$container['TIMEOUT'] = 5;
$container['MAX_REDIRECTS'] = 5;
$container['PROJECT'] = 'arachne';

$container['logger'] = function ($c) {
    $stream = new StreamHandler('php://stderr', $c['LOGGER_LEVEL']);
    $logger = new Logger('Arachne');
    $logger->pushHandler($stream);
    return $logger;
};

$container['storage_dir'] = function ($c) {
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $c['PROJECT'];
};

$container['frontier'] = function ($c) {
    $logger = $c['logger'];
    return new FrontierLogger(new InMemoryFrontier(new \SplPriorityQueue(), $c['storage_dir']), $logger);
};

$container['gatewayProfile'] = function ($c) {
    return new GatewayProfile();
};

$container['identities'] = function ($c) {
    $gatewayServer = GatewayServer::localhost();
    $gateway = new Gateway($c['eventDispatcher'], $gatewayServer, $c['gatewayProfile']);
    $defaultUserAgent = UserAgent::random();
    $identity = new Identity($gateway, $defaultUserAgent);
    return new IdentitiesCollection($identity);
};

$container['identityRotator'] = function ($c) {
    return new RoundRobinIdentityRotator($c['identities']);
};

$container['documentStorage'] = function ($c) {
    return new DocumentLogger(new InMemoryStorage($c['storage_dir']), $c['logger']);
};

$container['filter'] = function ($c) {
    return new FilterLogger(new InMemoryFilter($c['storage_dir']), $c['logger']);
};

$container['documentManager'] = function ($c) {
    $filesystem = $c['filesystem'];
    $blobsStorage = new Gaufrette($c['logger'], $filesystem);
    return new Manager($c['documentStorage'], $blobsStorage);
};

$container['requestFactory'] = function ($c) {
    return new DiactorosMessageFactory();
};

$container['responseFactory'] = function ($c) {
    return new DiactorosMessageFactory();
};

$container['filesystem'] = function ($c) {
    return new Filesystem(new Local($c['storage_dir'], true));
};

$container['scraper'] = function ($c) {
    $logger = $c['logger'];
    $client = new Guzzle($c['httpClient']);
    $identityRotator = $c['identityRotator'];
    $scheduler = $c['scheduler'];
    $docManager = $c['documentManager'];
    $requestFactory = $c['requestFactory'];
    $eventDispatcher = $c['eventDispatcher'];
    return new Basic($logger, $client, $identityRotator, $scheduler, $docManager, $requestFactory, $eventDispatcher);
};

$container['scheduler'] = function ($c) {
    return new Scheduler($c['frontier'], $c['filter'],
        $c['logger']);
};

$container['isServerError'] = $container->protect(function(ResponseInterface $response = null)
{
    return $response && $response->getStatusCode() >= 500;
});

$container['isConnectError'] = $container->protect(
    function(RequestException $exception = null)
    {
        return $exception instanceof ConnectException;
    }
);

$container['createRetryHandler'] = $container->protect(
    function(LoggerInterface $logger) use ($container)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            RequestException $exception = null
        ) use ($logger, $container) {
            if ($retries >= $container['HTTP_MAX_RETRIES']) {
                $logger->error(sprintf(
                    'Max number [%s] of retries reached for %s %s / %s',
                    $container['HTTP_MAX_RETRIES'],
                    $request->getMethod(),
                    $request->getUri(),
                    $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
                ));

                return false;
            }
            if (!($container['isServerError']($response) || $container['isConnectError']($exception))) {

                return false;
            }
            $logger->warning(sprintf(
                'Retrying %s %s %s/%s, %s',
                $request->getMethod(),
                $request->getUri(),
                $retries + 1,
                $container['HTTP_MAX_RETRIES'],
                $response ? 'status code: ' . $response->getStatusCode() : $exception->getMessage()
            ), [$request->getHeader('Host')[0]]);
            return true;
        };
    }
);

$container['createDelayHandler'] = $container->protect(function(LoggerInterface $logger)
{
    return function ($retries) use ($logger) {
        $delay = 3000 * (int)pow(2, $retries - 1);
        $logger->debug("Sleeping $delay milliseconds before retry");
        return $delay;
    };
});

$container['httpClient'] = function ($c) {
    $logger = $c['logger'];
    $stack = HandlerStack::create(new CurlHandler(
        ['handle_factory' => new CurlFactory(0)]
    ));
    $stack->push(Middleware::retry($c['createRetryHandler']($logger), $c['createDelayHandler']($logger)));

    $client = new Client([
        'handler' => $stack,
        'connect_timeout' => $c['CONNECT_TIMEOUT'],
        'timeout' => $c['TIMEOUT'],
        'http_errors' => false,
        'verify' => false,
        'allow_redirects' => [
            'max' => $c['MAX_REDIRECTS'],
            'protocols' => ['http', 'https'],
            'strict' => false,
            'track_redirects' => true,
        ],
    ]);
    return $client;
};

$container['eventDispatcher'] = function ($c) {
    $dispatcher = new WildcardEventDispatcher(new EventDispatcher());
    $logger = $c['logger'];
    $dispatcher->addListener('#', function(Event $event) use ($logger) {
        if ($event instanceof EventSummaryInterface) {
            $logger->debug($event->getSummary());
        }
    });
    return $dispatcher;
};
