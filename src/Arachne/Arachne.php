<?php

namespace Arachne;

use Arachne\Exceptions\GatewayException;
use Arachne\Exceptions\NoGatewaysLeftException;
use Http\Message\RequestFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
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


    /**
     * Arachne constructor.
     * @param LoggerInterface $logger
     * @param ClientInterface $client
     * @param Scheduler $scheduler
     * @param Document\Manager $docManager
     * @param RequestFactory $requestFactory
     */
    public function __construct(
        LoggerInterface $logger,
        ClientInterface $client,
        Scheduler $scheduler,
        Document\Manager $docManager,
        RequestFactory $requestFactory
    ) {
        $this->logger = $logger;
        $this->client = $client;
        $this->scheduler = $scheduler;
        $this->docManager = $docManager;
        $this->requestFactory = $requestFactory;
        register_shutdown_function(function () use ($scheduler) {
            $this->logger->debug('Shutting down');
            if ($this->reschedulePreviouslyFailedResources && count($this->failedResources)) {
                $this->logger->debug('Rescheduling failed resources for the next run');
                foreach ($this->failedResources as $failedResource) {
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


    public function getCurrentItem()
    {
        return $this->currentItem;
    }

    /**
     * @param \Arachne\HttpResource|HttpResource $resource
     */
    public function processSingeItem(HttpResource $resource)
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
            $this->failedResources[] = $resource;
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
        ResponseInterface $response = null,
        \Exception $exception = null
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
     * @param HttpResource[] $resources
     * @return Arachne
     */
    public function scrape(HttpResource ...$resources)
    {
        foreach ($resources as $resource) {
            $this->scheduler->schedule($resource, FrontierInterface::PRIORITY_HIGH);
        }
        $this->run();
        return $this;
    }

    /**
     * @param string ...$urls
     * @return $this
     */
    public function scrapeUrls(string  ...$urls)
    {
        foreach ($urls as $url) {
            $resource = HttpResource::fromUrl($url);
            $this->scheduler->schedule($resource, FrontierInterface::PRIORITY_HIGH);
        }
        $this->run();
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
                if (empty($resource)) {
                    break;
                }
                $batch[] = $resource;
                $checkIfFrontierEmpty = true;
            }
            $this->processBatch(...$batch);
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
                $this->scheduler->scheduleNewResources($newResource, $priority);
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
    protected function saveBlobs(HttpResource $resource, ResultSet $resultSet, ResponseInterface $response)
    {
        if ($resultSet->isBlob() || ($resource->getType() === 'blob')) {
            $blobsStorage = $this->docManager->getBlobsStorage();
            $path = $blobsStorage->write($resource, (string)$response->getBody());
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
        ResponseInterface $response = null,
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
        $this->shutdownOnException && $this->shutdown();
    }

    protected function handleHttpSuccess(HttpResource $resource, ResponseInterface $response = null)
    {
        $resultSet = new ResultSet($resource, $this->requestFactory);
        if ($handler = $this->getSuccessHandler($resource->getType())) {
            try {
                $handler($response, $resultSet);
                $this->logger->info(sprintf('Resource [%s] %s has been parsed ', $resource->getType(),
                    $resource->getUrl()));
                $this->scheduleNewResources($resultSet);
                $this->saveParsedItems($resultSet);
                $this->saveBlobs($resource, $resultSet, $response);

            } catch (NestedValidationException $exception) {
                throw new ParsingResponseException($exception->getFullMessage(), 0, $exception);
            } catch (\Exception $exception) {
                throw new ParsingResponseException($exception->getMessage(), 0, $exception);
            }
        }
    }

    /**
     * @param HttpResource $resource
     * @param $response
     * @param $logger
     */
    protected function handleResponse(HttpResource $resource, ResponseInterface $response = null)
    {
        if ((!empty($response)) && $response->getStatusCode() === 200) {
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
     * @param $batch
     */
    protected function processBatch(HttpResource ... $resources)
    {
        // @attention !!! You can not bind one Resource to another by passing thrid parameter to
        // $resultSet->addResource(...) if you use InMemory adapter
        //
        $wp = new \QXS\WorkerPool\WorkerPool();
        $batchSize = $this->getConcurrency() ?: count($resources);
        $wp->setWorkerPoolSize($batchSize)
            ->create(new \QXS\WorkerPool\ClosureWorker(
                /**
                 * @param mixed $input the input from the WorkerPool::run() Method
                 * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to synchronize calls accross all workers
                 * @param \ArrayObject $storage a persistent storage for the current child process
                 */
                    function ($item, $semaphore, $storage) {
                        //https://jira.mongodb.org/browse/PHPC-625
                        //When using pcntl_fork() with the new MongoDB\Driver it is impossible to create a new
                        // MongoDB manager in the child process if one has been opened in the parent process.
                        //the newly created on in the child process seem to share a socket to MongoDB and throw
                        // errors based on receiving each others responses.
//                                $filter = $this->getFilter();
//                                $frontier = $this->getFrontier();
//                                $resultsStorage = $this->getResultsStorage();
//                                foreach ([$filter, $frontier, $resultsStorage] as $_) {
//                                    if (is_callable([$_, 'setManager'])) {
//                                        $_->setManager(new \MongoDB\Driver\Manager("mongodb://localhost:27017/?x=" . posix_getpid()));
//                                    }
//                                }
                        $this->processSingeItem($item);
                        return $item;
                    }
                )
            );
        foreach ($resources as $resource) {
            $wp->run($resource);
        }
        $wp->waitForAllWorkers(); // wait for all workers
//        foreach ($wp as $result) {
//            yield $result;
//        }
    }

    /**
     * @param $mode
     * @return Arachne
     */
    public function prepareEnv($mode)
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
