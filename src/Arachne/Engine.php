<?php

namespace Arachne;

use Arachne\Client\ClientInterface;
use Arachne\Identity\IdentityRotatorInterface;
use Arachne\Item\ItemInterface;
use Arachne\PostProcessor\PostProcessorInterface;
use Arachne\Processor\ProcessorInterface;
use Http\Message\RequestFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\Frontier\FrontierInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class Arachne
 * @package Arachne
 */
abstract class Engine
{
    public bool $reschedulePreviouslyFailedResources = true;
    public bool $shutdownOnException = false;

    protected array $failedResources = [];

    protected int $concurrency = 10;

    protected array $handlers = [];
    /**
     * @var ProcessorInterface[]
     */
    protected array $processors = [];

    /**
     * @var PostProcessorInterface[]
     */
    protected array $postProcessors = [];


    /**
     * Arachne constructor.
     * @param LoggerInterface $logger
     * @param ClientInterface $client
     * @param IdentityRotatorInterface $identityRotator
     * @param Scheduler $scheduler
     * @param RequestFactory $requestFactory
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected ClientInterface $client,
        protected IdentityRotatorInterface $identityRotator,
        protected Scheduler $scheduler,
        protected Document\Manager $docManager,
        protected RequestFactory $requestFactory,
        protected EventDispatcherInterface $eventDispatcher
    ) {
        $this->onShutdown();
    }

    protected function onShutdown()
    {
        register_shutdown_function(function () {
            $this->logger->debug('Shutting down process');
            if ($this->reschedulePreviouslyFailedResources && count($this->failedResources)) {
                $this->logger->debug('Rescheduling failed resources for the next run');
                $failedResources = $this->failedResources;
                $this->failedResources = [];
                foreach ($failedResources as $failedResource) {
                    $this->scheduler->schedule($failedResource);
                }
            }
            $frontier = $this->scheduler->getFrontier();
            if ($frontier instanceof ShutdownAware) {
                $this->logger->debug('Handling Frontier shutdown');
                $frontier->onShutdown();
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

    /**
     * @param HttpResource[] $resources
     */
    abstract public function process(HttpResource ... $resources);

    protected function handleException(
        HttpResource $resource,
        ?ResponseInterface $response = null,
        ?\Exception $exception = null
    ): void {
        if ($handler = $this->getExceptionHandler($resource->getType())) {
            try {
                $handler($response, $resource, $exception);
                $this->logger->debug(sprintf('Handler of Exception [%s] has been called. Resource %s ',
                    $exception->getMessage(), $resource->getUrl()));
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Failed to handle Exception. Resource %s. Reason: %s', $resource->getUrl(),
                    $e->getMessage()));
            }
        } elseif ($exception !== null) {
            $this->logger->error(sprintf('Exception: %s. Resource %s', $exception->getMessage(), $resource->getUrl()));
        }
        $this->shutdownOnException && $this->shutdown();
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getExceptionHandler(string $type): ?callable
    {
        return $this->handlers[$type]['exception'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getSuccessHandler(string $type): ?callable
    {
        return $this->handlers[$type]['success'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getAlwaysHandler(string $type): ?callable
    {
        return $this->handlers[$type]['always'] ?? null;
    }

    /**
     * @param string $type
     * @return null|callable
     */
    protected function getFailHandler(string $type): ?callable
    {
        return $this->handlers[$type]['fail'] ?? null;
    }


    /**
     * @param HttpResource ...$resources
     * @return Engine
     */
    public function scrape(HttpResource ...$resources): static
    {
        $this->schedule(...$resources);
        $this->run();
        return $this;
    }

    /**
     * @param HttpResource ...$resources
     * @return Engine
     */
    public function schedule(HttpResource ...$resources): static
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
    public function scrapeUrls(string  ...$urls): static
    {
        $this->scheduleUrls(...$urls);
        $this->run();
        return $this;
    }

    /**
     * @param string ...$urls
     * @return $this
     */
    public function scheduleUrls(string ...$urls): static
    {
        foreach ($urls as $url) {
            $resource = HttpResource::fromUrl($url);
            $this->scheduler->schedule($resource, FrontierInterface::PRIORITY_HIGH);
        }
        return $this;
    }

    protected function run(): void
    {
        do {
            $checkIfFrontierEmpty = false;
            $batch = [];
            for ($i = 0; $i < $this->concurrency; $i++) {
                try {
                    $resource = $this->scheduler->nextItem();
                } catch (\Exception $exception) {
                    $this->logger->critical($exception->getMessage());
                    continue;
                }
                if ($resource === null) {
                    break;
                }
                $batch[] = $resource;
                $checkIfFrontierEmpty = true;
            }
            $batch && $this->process(...$batch);
        } while ($checkIfFrontierEmpty);
        $this->logger->debug('No more Items to process');
        if (count($this->postProcessors)) {
            $this->logger->debug('Starting data post processing');
            $documentsStorage = $this->docManager->getDocStorage();
            foreach ($documentsStorage->getIterator() as $document) {
                foreach ($this->postProcessors as $postProcessor) {
                    $document = $postProcessor->processData($document);
                }
            }
        }
    }

    /**
     * @param array $config
     * @return Engine
     */
    public function addHandlers(array $config): static
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
    protected function scheduleNewResources(ResultSet $resultSet): void
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
    protected function saveParsedItems($resultSet): void
    {
        foreach ($resultSet->getItems() as $item) {
            /** @var ItemInterface $item */

            if ($this->docManager->documentExists($item->getType(), $item->getId())) {
                $this->docManager->updateDocument(
                    $item->getType(), $item->getId(),
                    $item->asArray()
                );
            } else {
                $this->docManager->createDocument($item->getType(), $item->getId(), $item->asArray());
            }
        }
    }

    /**
     * @param ResultSet $resultSet
     */
    protected function saveBlobs(ResultSet $resultSet): void
    {
        if (!$resultSet->isBlob()) {
            return;
        }
        $blobsStorage = $this->docManager->getBlobsStorage();
        $resource = $resultSet->getResource();
        $path = $blobsStorage->write($resource, (string) $resultSet->getBlob());
        $this->logger->debug(sprintf('Saving blob resource [%s] to [%s]', $resource->getUrl(), $path));

        if ($itemId = $resource->getMeta('related_id', null)) {
            $itemType = $resource->getMeta('related_type');
            $oldData = $this->docManager->getDocument($itemType, $itemId);
            if (!empty($oldData)) {
                $blobs = [];
                if (isset($oldData['blobs'])) {
                    $blobs = $oldData['blobs'];
                }
                $blobs[sha1($resource->getUrl())] = ['path' => $path, 'origUrl' => $resource->getUrl()];
                $this->docManager->updateDocument(
                    $itemType, $itemId,
                    ['blobs' => $blobs]
                );
            }
        }
    }

 /**
     * @param HttpResource $resource
     * @param $resultSet
     * @param $response
     */
    public function addProcessor(ProcessorInterface $processor): static
    {
        $this->processors[] = $processor;
        return $this;
    }

    public function addPostProcessor(PostProcessorInterface $processor): static
    {
        $this->postProcessors[] = $processor;
        return $this;
    }

    protected function handleHttpFail(
        HttpResource $resource,
        ?ResponseInterface $response = null,
        \Exception $exception = null
    ): void {
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

    protected function handleHttpSuccess(HttpResource $resource, ResponseInterface $response): void
    {
        $resultSet = new ResultSet($resource, $this->requestFactory);
        if ($handler = $this->getSuccessHandler($resource->getType())) {
            try {
                $handler($response, $resultSet);
                $this->logger->info(sprintf('Resource [%s] %s has been parsed ', $resource->getType(),
                    $resource->getUrl()));
                if ($markedVisited = $resultSet->getMarkedAsVisited()) {
                    foreach ($markedVisited as $visited) {
                        $this->scheduler->getFilter()->add('visited', $visited);
                        $this->scheduler->getFilter()->remove('scheduled', $visited);
                    }
                }
                $this->scheduleNewResources($resultSet);
                $this->saveParsedItems($resultSet);
                $this->saveBlobs($resultSet);
                foreach ($this->processors as $processor) {
                    $resultSet = $processor->processResultSet($resultSet);
                }

            } catch (NestedValidationException $exception) {
                throw new ParsingResponseException($exception->getFullMessage(), 0, $exception);
            } catch (\Exception $exception) {
                throw new ParsingResponseException($exception->getMessage(), 0, $exception);
            }
        }
    }

    protected function handleAnyway(HttpResource $resource, ?ResponseInterface $response = null): void
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

    public function shutdown()
    {
        $this->logger->debug('Shutting down');
        die();
    }

    /**
     * @param $mode
     * @return Engine
     */
    public function prepareEnv(string $mode = Mode::RESUME): static
    {
        if ($mode === Mode::RESUME) {
        }
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
