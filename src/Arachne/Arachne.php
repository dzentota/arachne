<?php

namespace Arachne;

use Arachne\Exceptions\GatewayException;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Identity\Identity;
use Arachne\Identity\IdentityRotatorInterface;
use Http\Message\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use QXS\WorkerPool\WorkerPool;
use Respect\Validation\Exceptions\NestedValidationException;
use Arachne\Client\ClientInterface;
use Arachne\Exceptions\HttpRequestException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\Frontier\FrontierInterface;

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
     * @var RequestInterface
     */
    private $lastRequest;
    /**
     * @var ResponseInterface
     */
    private $lastResponse;

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

    private $parentPid = 0;

    /**
     * @var WorkerPool
     */
    private $workerPool;

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
        RequestFactory $requestFactory
    ) {
        $this->parentPid = getmypid();
        $this->logger = $logger;
        $this->client = $client;
        $this->identityRotator = $identityRotator;
        $this->scheduler = $scheduler;
        $this->docManager = $docManager;
        $this->requestFactory = $requestFactory;
        $this->logger->debug('Started process with PID: ' . $this->parentPid);
        register_shutdown_function(function () use ($scheduler) {
            if ($this->parentPid === getmypid()) {
                $this->logger->debug('Shutting down parent process');
                if ($this->reschedulePreviouslyFailedResources && count($this->failedResources)) {
                    $this->logger->debug('Rescheduling failed resources for the next run');
                    $failedResources = $this->failedResources;
                    $this->failedResources = [];
                    foreach ($failedResources as $failedResource) {
                        $this->scheduler->schedule($failedResource);
                    }
                }
            } else {
                $this->logger->debug('Shutting down worker process');
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
     */
    public function processSingleItem(HttpResource $resource)
    {
        $this->lastRequest = $request = $resource->getHttpRequest();
        $response = null;
        try {
            $this->lastResponse = $response = $this->client->sendRequest($request);
            $this->scheduler->markVisited($resource);
            $this->handleResponse($resource, $response);
        } catch (HttpRequestException $exception) {
            $this->logger->error('Got Exception during sending the Request ' . $resource->getUrl());
            $this->logger->error('Exception message: ' . ($exception->getPrevious() ?
                    $exception->getPrevious()->getMessage() : $exception->getMessage()));
            $this->handleHttpFail($resource, $response, $exception);
        } catch (ParsingResponseException $exception) {
            $this->logger->critical('Got Exception during parsing the Response from ' . $resource->getUrl());
            $this->logger->critical('Exception message: ' . $exception->getMessage());
            $this->handleException($resource, $response, $exception);
        } catch (NoGatewaysLeftException $exception) {
            $this->handleException($resource, $response, $exception);
            $this->failedResources[] = $resource;
            //if script is still running due to shutdownOnException === false
            $this->shutdown();
        } catch (GatewayException $exception) {
            $this->handleException($resource, $response, $exception);
            $this->failedResources[] = $resource;
        } catch (\Exception $exception) {
            $this->logger->critical('Got Exception during sending the Request ' . $resource->getUrl());
            $this->logger->critical('Exception message: ' . $exception->getMessage());
            $this->failedResources[] = $resource;
            $this->handleException($resource, $response, $exception);
        } finally {
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
            $batch && $this->processBatch(...$batch);
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

    /**
     * @param HttpResource[] $resources
     * @throws \QXS\WorkerPool\WorkerPoolException
     */
    protected function processBatch(HttpResource ... $resources)
    {
        $wp = $this->getWorkerPool();
        foreach ($resources as $resource) {
            /**
             * Identity rotator can not be used directly in worker because all workers share
             * parent's memory and will have the same list of proxies and other settings in such case
             */
            $identity = $this->identityRotator->switchIdentityFor($resource->getHttpRequest());
            $this->client->ensureIdentityIsCompatibleWithClient($identity);
            /**
             * Prepare config because Identity can not be serialized, so can not be passed to worker
             */
            $config = $this->client->prepareConfig([], $identity);
            $wp->run(['resource' => $resource, 'config' => $config]);
        }
        $wp->waitForAllWorkers(); // wait for all workers
        foreach ($wp as $result) {

            try {
                if (empty($result['data'])) {
                    $this->logger->error('Got empty result from Worker');
                    continue;
                }
                $response = null;
                /**
                 * @var HttpResource $resource
                 * @var string $serializedResponse
                 * @var \Exception $exception
                 */
                extract($result['data'], EXTR_OVERWRITE);
                if (isset($serializedResponse)) {
                    $response = \Zend\Diactoros\Response\Serializer::fromString($serializedResponse);
                }
                if (isset($exceptionData)) {
                    $exception = new $exceptionData['class']($exceptionData['message']);
                }
                //We should somehow differ Fails from Exceptions here
                if (!isset($exception)) {
                    $this->scheduler->markVisited($resource);
                    if (isset($response) && $response->getStatusCode() === 200) {
                        $this->handleHttpSuccess($resource, $response);
                    } else {
                        $this->handleHttpFail($resource, $response);
                    }
                } else {
                    $this->failedResources[] = $resource;
                    /**
                     * @var $exception \Exception
                     */
                    switch (get_class($exception)) {
                        case HttpRequestException::class:
                            $this->handleHttpFail($resource, $response, $exception);
                            break;
                        case NoGatewaysLeftException::class:
                            $this->handleException($resource, $response, $exception);
                            //if script is still running due to shutdownOnException === false
                            $this->shutdown();
                            break;
                        case GatewayException::class:
                            $this->handleException($resource, $response, $exception);
                            break;
                    }

                }

            } catch (ParsingResponseException $exception) {
                $this->logger->critical('Got Exception during parsing the Response from ' . $resource->getUrl());
                $this->logger->critical('Exception message: ' . $exception->getMessage());
                $this->handleException($resource, $response, $exception);
            } catch (\Exception $exception) {
                $this->logger->critical('Got Exception during sending the Request ' . $resource->getUrl());
                $this->logger->critical('Exception message: ' . $exception->getMessage());
                $this->failedResources[] = $resource;
                $this->handleException($resource, $response, $exception);
            } finally {
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
        }
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

    /**
     * @return WorkerPool
     * @throws \QXS\WorkerPool\WorkerPoolException
     */
    protected function getWorkerPool(): WorkerPool
    {
        /**
         * Skip creation of new Workers to prevent error:
         * socket_create_pair(): unable to create socket pair [24]: Too many open files
         */
        if (!isset($this->workerPool)) {
            $this->workerPool = new \QXS\WorkerPool\WorkerPool();
            $this->workerPool->setWorkerPoolSize($this->getConcurrency())
                ->create(new \QXS\WorkerPool\ClosureWorker(
                    /**
                     * @param mixed $input the input from the WorkerPool::run() Method
                     * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to synchronize calls accross all workers
                     * @param \ArrayObject $storage a persistent storage for the current child process
                     */
                        function ($input, $semaphore, $storage) {
                            /**
                             * @var HttpResource $resource
                             */
                            $resource = $input['resource'];
                            /**
                             * @var Identity $identity
                             */
                            $requestConfig = $input['config'];
                            $response = null;
                            $request = $resource->getHttpRequest();
                            try {
                                $response = $this->client->sendRequest($request, $requestConfig);
                                $this->identityRotator->evaluateResult($response);
                                /**
                                 * Serialize Response object to forward stream contents to parent process
                                 */
                                return ['resource' => $resource, 'serializedResponse' => \Zend\Diactoros\Response\Serializer::toString($response)];
                            } catch (\Exception $exception) {
                                $this->identityRotator->evaluateResult(null);
                                $this->logger->error('Got Exception: ' . $exception->getMessage(). ', during sending the Request ' . $resource->getUrl());
                                if ($exception->getPrevious()) {
                                    $this->logger->error('Previous exception message: ' . $exception->getPrevious()->getMessage());
                                }
                                return ['resource' => $resource, 'serializedResponse' => $response? \Zend\Diactoros\Response\Serializer::toString($response) : null, 'exceptionData' => ['class' => get_class($exception), 'message' => $exception->getMessage()]];
                            }
                        }
                    )
                );
        }
        return $this->workerPool;
    }

}
