<?php

namespace Arachne;

use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Gateway\Localhost;
use Arachne\Identity\Identity;
use Arachne\Identity\IdentityRotatorInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Http\Message\RequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use GuzzleHttp\ClientInterface;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\Frontier\FrontierInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Arachne
 * @package Arachne
 */
class Arachne
{
    public $reschedulePreviouslyFailedResources = true;
    public $shutdownOnException = false;

    private $failedResources = [];
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var IdentityRotatorInterface
     */
    private $identityRotator;

    /**
     * @var Scheduler
     */
    private $scheduler;

    /**
     * @var Document\Manager
     */
    private $docManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    private $requestFactory;

    /**
     * @var
     */
    private $currentItem;

    /**
     * @var int
     */
    private $concurrency = 10;

    /**
     * @var array
     */
    private $handlers = [];

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    /**
     * Arachne constructor.
     * @param LoggerInterface $logger
     * @param ClientInterface $client
     * @param IdentityRotatorInterface $identityRotator
     * @param Scheduler $scheduler
     * @param Document\Manager $docManager
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        LoggerInterface $logger,
        ClientInterface $client,
        IdentityRotatorInterface $identityRotator,
        Scheduler $scheduler,
        Document\Manager $docManager,
        RequestFactory $requestFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = $logger;
        $this->client = $client;
        $this->identityRotator = $identityRotator;
        $this->scheduler = $scheduler;
        $this->docManager = $docManager;
        $this->requestFactory = $requestFactory;
        $this->eventDispatcher = $eventDispatcher;
        register_shutdown_function(function () use ($scheduler) {
                $this->logger->debug('Shutting down process');
                if ($this->reschedulePreviouslyFailedResources && count($this->failedResources)) {
                    $this->logger->debug('Rescheduling failed resources for the next run');
                    $failedResources = $this->failedResources;
                    $this->failedResources = [];
                    foreach ($failedResources as $failedResource) {
                        $this->scheduler->schedule($failedResource);
                    }
                }
        });
    }

    /**
     * @return int
     */
    public function getConcurrency(): int
    {
        return $this->concurrency;
    }

    public function setConcurrency(int $concurrency): self
    {
        $this->concurrency = $concurrency;
        return $this;
    }

    public function getCurrentItem()
    {
        return $this->currentItem;
    }

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     * @param array $requestConfig
     */
    public function processSingleItem(HttpResource $resource, $requestConfig = [])
    {
        $this->processBatch([$resource], $requestConfig);
    }

    protected function handleException(
        HttpResource $resource,
        ?ResponseInterface $response = null,
        ?\Exception $exception = null
    ) {
        if ($handler = $this->getExceptionHandler($resource->getType())) {
            try {
                $handler($response, $resource, $exception);
                $this->logger->debug(sprintf('Handler of Exception [%s] has been called. Resource %s ',
                    $exception->getMessage(), $resource->getUrl()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to handle Exception. Resource %s. Reason: %s', $resource->getUrl(),
                    $e->getMessage()));
            }
        }
        $this->shutdownOnException && $this->shutdown();
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getExceptionHandler(string $type)
    {
        return $this->handlers[$type]['exception'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getSuccessHandler(string $type)
    {
        return $this->handlers[$type]['success'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getAlwaysHandler(string $type)
    {
        return $this->handlers[$type]['always'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getFailHandler(string $type)
    {
        return $this->handlers[$type]['fail'] ?? null;
    }


    /**
     * @param null $type
     * @return $this
     */
    public function dumpDocuments($type = null)
    {
        $documentsStorage = $this->docManager->getDocStorage();
        foreach ($documentsStorage->getIterator($type) as $document) {
            var_export($document);
        }
        return $this;
    }


    /**
     * @param HttpResource ...$resources
     * @return Arachne
     */
    public function scrape(HttpResource ...$resources)
    {
        $this->schedule(...$resources);
        $this->run();
        return $this;
    }

    /**
     * @param HttpResource ...$resources
     * @return Arachne
     */
    public function schedule(HttpResource ...$resources)
    {
        foreach ($resources as $resource) {
            $this->scheduler->schedule($resource, FrontierInterface::PRIORITY_HIGH);
        }
        return $this;
    }

    /**
     * @param string ...$urls
     * @return $this
     */
    public function scrapeUrls(string  ...$urls)
    {
        $this->scheduleUrls(...$urls);
        $this->run();
        return $this;
    }

    /**
     * @param string ...$urls
     * @return $this
     */
    public function scheduleUrls(string ...$urls)
    {
        foreach ($urls as $url) {
            $resource = HttpResource::fromUrl($url);
            $this->scheduler->schedule($resource, FrontierInterface::PRIORITY_HIGH);
        }
        return $this;
    }

    /**
     *
     */
    protected function run()
    {
        do {
            $checkIfFrontierEmpty = false;
            $batch = [];
            for ($i = 0; $i < $this->concurrency; $i++) {
                $resource = $this->scheduler->nextItem();
                if ($resource === null) {
                    break;
                }
                $batch[] = $resource;
                $checkIfFrontierEmpty = true;
            }
            $batch && $this->processBatch($batch);
        } while ($checkIfFrontierEmpty);
        $this->logger->debug('No more Items to process');
    }

    /**
     * @param array $config
     * @return Arachne
     */
    public function addHandlers(array $config)
    {
        foreach ($config as $key => $value) {
            if (in_array($key, ['success', 'fail', 'always'])) {
                if (!is_callable($value)) {
                    throw new \InvalidArgumentException(sprintf('%s must be a valid callback, %s given', $key,
                        gettype($value)));
                }
                $this->handlers['default'][$key] = $value;
            } else {
                if (strpos($key, ':')) {
                    list($callbackType, $resourceType) = explode(':', $key);
                    if (in_array($callbackType, ['success', 'fail', 'always'])) {
                        if (!is_callable($value)) {
                            throw new \InvalidArgumentException(sprintf('%s must be a valid callback, %s given', $key,
                                gettype($value)));
                        }
                        $this->handlers[$resourceType][$callbackType] = $value;
                    }
                }
            }

        }
        return $this;
    }

    /**
     * @param $resultSet
     */
    protected function scheduleNewResources(ResultSet $resultSet)
    {
        foreach ($resultSet->getParsedResources() as $priority => $resData) {
            foreach ($resData as $newResource) {
                $this->scheduler->scheduleNewResource($newResource, $priority);
            }
        }
    }

    /**
     * @param ResultSet $resultSet
     */
    protected function saveParsedItems($resultSet)
    {
        foreach ($resultSet->getItems() as $item) {
            /** @var Item $item */

            if ($this->docManager->documentExists($item->type, $item->id)) {
                $this->docManager->updateDocument(
                    $item->type, $item->id,
                    $item->asArray()
                );
            } else {
                $this->docManager->createDocument($item->type, $item->id, $item->asArray());
            }
        }
    }

    /**
     * @param HttpResource $resource
     * @param $resultSet
     * @param $response
     */
    protected function saveBlobs(HttpResource $resource, ResultSet $resultSet, string $content)
    {
        if ($resultSet->isBlob() || ($resource->getType() === 'blob')) {
            $blobsStorage = $this->docManager->getBlobsStorage();
            $path = $blobsStorage->write($resource, $content);
            $this->logger->debug(sprintf('Saving blob resource [%s] to [%s]', $resource->getUrl(), $path));
            if ($itemId = $resource->getMeta('related_id', null)) {
                $itemType = $resource->getMeta('related_type');
                $oldData = $this->docManager->getDocument($itemType, $itemId);
                if (!empty($oldData)) {
                    $blobs = [];
                    if (isset($oldData['blobs'])) {
                        $blobs = $oldData['blobs'];
                    }
                    $blobs[md5($resource->getUrl())] = ['path' => $path, 'origUrl' => $resource->getUrl()];
                    $this->docManager->updateDocument(
                        $itemType, $itemId,
                        ['blobs' => $blobs]
                    );
                }
            }
        }
    }

    protected function handleHttpFail(
        HttpResource $resource,
        ?ResponseInterface $response = null,
        \Exception $exception = null
    ) {
        if ($handler = $this->getFailHandler($resource->getType())) {
            try {
                $handler($response, $resource, $exception);
                $this->logger->debug(sprintf('Handler for fail of the resource %s has been called',
                    $resource->getUrl()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to handle failing of resource %s. Reason: %s', $resource->getUrl(),
                    $e->getMessage()));
            }
        }
        $this->failedResources[] = $resource;
        $this->shutdownOnException && $this->shutdown();
    }

    protected function handleHttpSuccess(HttpResource $resource, ResponseInterface $response)
    {
        $resultSet = new ResultSet($resource, $this->requestFactory);
        if ($handler = $this->getSuccessHandler($resource->getType())) {
            try {
                $handler($response, $resultSet);
                $this->logger->info(sprintf('Resource [%s] %s has been parsed ', $resource->getType(),
                    $resource->getUrl()));
                $this->scheduleNewResources($resultSet);
                $this->saveParsedItems($resultSet);
                $this->saveBlobs($resource, $resultSet, (string)$response->getBody());

            } catch (NestedValidationException $exception) {
                throw new ParsingResponseException($exception->getFullMessage(), 0, $exception);
            } catch (\Exception $exception) {
                throw new ParsingResponseException($exception->getMessage(), 0, $exception);
            }
        }
    }

    protected function handleAnyway(HttpResource $resource, ?ResponseInterface $response = null)
    {
        if ($handler = $this->getAlwaysHandler($resource->getType())) {
            try {
                $handler($response, $resource);
                $this->logger->debug(sprintf('Handler of the resource %s has been called', $resource->getUrl()));
            } catch (\Exception $exception) {
                $this->logger->critical(sprintf('Failed to handle resource %s. Reason: %s', $resource->getUrl(),
                    $exception->getMessage()));
                $this->handleException($resource, $response, $exception);
            }
        }
    }

    /**
     * @param HttpResource $resource
     * @param ResponseInterface $response
     * @throws ParsingResponseException
     */
    protected function handleResponse(HttpResource $resource, ResponseInterface $response = null)
    {
        if (!empty($response) && $response->getStatusCode() === 200) {
            $this->handleHttpSuccess($resource, $response);
        } else {
            $this->handleHttpFail($resource, $response);
        }
    }

    public function shutdown()
    {
        $this->logger->debug('Shutting down');
        die();
    }

    public function prepareConfig(array $requestConfig, ?Identity $identity): array
    {
        $config['allow_redirects']['referer'] = $requestConfig['allow_redirects']['referer'] ??
            ($identity ? $identity->isSendReferer() : true);
        $config['headers'] = $requestConfig['headers'] ??
            ($identity ? $identity->getDefaultRequestHeaders() : []);
        $config['headers']['User-Agent'] = $requestConfig['headers']['User-Agent'] ??
            ($identity ? $identity->getUserAgent() : 'Arachne');
        if ($identity !== null) {
            $proxy = $identity->getGateway()->getGatewayServer();
            if (!($proxy instanceof Localhost)) {
                $config['proxy'] = (string)$proxy;
            }
        }
        if (isset($requestConfig['cookies'])) {
            $config['cookies'] = $requestConfig['cookies'];
        } else {
            $config['cookies'] = $identity !== null && $identity->areCookiesEnabled()? new CookieJar() : false;
        }
        return $config;
    }

    /**
     * @param HttpResource[] $resources
     * @param array $requestConfig
     */
    public function processBatch($resources, $requestConfig = [])
    {
        $requests = (function () use ($resources, $requestConfig){
            foreach ($resources as $resource) {
                try {
                    $identity = $this->identityRotator->switchIdentityFor($resource->getHttpRequest());
                    $config = $this->prepareConfig($requestConfig, $identity);
                    $request = $resource->getHttpRequest();
                    $this->logger->info('Loading resource from URL ' . $request->getUri());
                    $this->logger->debug('Request config: ' . (empty($config) ? '<EMPTY>' : var_export(
                            array_map(function ($param) {
                                return ($param instanceof CookieJar)? true : $param;
                            }, $config), true)));
                    $this->eventDispatcher->dispatch(RequestPrepared::name, new RequestPrepared($request, $config));
                    yield $this->client->sendAsync($request, $config)
                        ->then(
                            function (ResponseInterface $response) use ($resource, $identity){
                                $this->eventDispatcher->dispatch(ResponseReceived::name, new ResponseReceived($resource->getHttpRequest(), $response));
                                $this->identityRotator->evaluateResult($identity, $response);
                                $this->scheduler->markVisited($resource);
                                if ($response->getStatusCode() === 200) {
                                    try {
                                        $this->handleHttpSuccess($resource, $response);
                                    } catch (ParsingResponseException $exception) {
                                        $this->handleException($resource, $response, $exception);
                                    }
                                } else {
                                    $this->handleHttpFail($resource, $response);
                                }
                                $this->handleAnyway($resource, $response);
                            },
                            function (RequestException $reason) use ($resource, $identity) {
                                if (null !== $reason->getResponse()) {
                                    $this->eventDispatcher->dispatch(ResponseReceived::name,
                                        new ResponseReceived($resource->getHttpRequest(), $reason->getResponse()));
                                }
                                try {
                                    $this->identityRotator->evaluateResult($identity, null);
                                } catch (\Exception $exception) {
                                    $this->handleException($resource, $reason->getResponse(), $exception);
                                }
                                $this->handleException($resource, $reason->getResponse(), $reason);
                                $this->handleAnyway($resource, $reason->getResponse());
                            }
                        );
                } catch (NoGatewaysLeftException $exception) {
                    $this->handleException($resource, null, $exception);
                    $this->shutdown();
                }
            }
        })();

        \GuzzleHttp\Promise\settle($requests)->wait();
    }

    /**
     * @param $mode
     * @return Arachne
     */
    public function prepareEnv($mode = Mode::RESUME)
    {
        if ($mode === Mode::REFRESH) {
            $this->scheduler->clear();
        }
        if ($mode === Mode::CLEAR) {
            $this->scheduler->clear();
            $this->docManager->getDocStorage()->clear();
            $this->docManager->getBlobsStorage()->clear();
        }
        return $this;
    }

}
