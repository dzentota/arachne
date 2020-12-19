<?php

namespace Arachne\Engine;

use Arachne\Client\ClientInterface;
use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Document;
use Arachne\Engine;
use Arachne\Exceptions\NoGatewaysLeftException;
use Arachne\Exceptions\ParsingResponseException;
use Arachne\HttpResource;
use Arachne\Identity\IdentityRotatorInterface;
use Arachne\Scheduler;
use GuzzleHttp\Cookie\CookieJar;
use Http\Message\RequestFactory;
use Psr\Log\LoggerInterface;
use QXS\WorkerPool\ClosureWorker;
use QXS\WorkerPool\WorkerPool;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Zend\Diactoros\Response\Serializer;

class Parallel extends Engine
{
    private $parentPid = 0;

    public function __construct(
        LoggerInterface $logger,
        ClientInterface $client,
        IdentityRotatorInterface $identityRotator,
        Scheduler $scheduler,
        Document\Manager $docManager,
        RequestFactory $requestFactory,
        EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct($logger, $client, $identityRotator, $scheduler, $docManager, $requestFactory,
            $eventDispatcher);
        $this->parentPid = getmypid();
        $this->logger->debug('Started process with PID: ' . $this->parentPid);
    }

    protected function onShutdown()
    {
        register_shutdown_function(function () {
            if ($this->parentPid === getmypid()) {
                $this->logger->debug('Shutting down parent process');
                if ($this->reschedulePreviouslyFailedResources && count($this->failedResources)) {
                    $this->logger->debug('Rescheduling failed resources for the next run');
                    $failedResources[] = $this->failedResources;
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

    public function process(HttpResource ...$resources)
    {
        $wp = new WorkerPool();
        $batchSize = $this->getConcurrency() ?: count($resources);
        $batchSize = $batchSize > count($resources)? count($resources) :  $batchSize;
        $wp->setWorkerPoolSize($batchSize)
            ->create(new ClosureWorker(
                /**
                 * @param array $input the input from the WorkerPool::run() Method
                 * @param \QXS\WorkerPool\Semaphore $semaphore the semaphore to synchronize calls accross all workers
                 * @param \ArrayObject $storage a persistent storage for the current child process
                 */
                    function (array $input, $semaphore, $storage) {
                        $resource = $input['resource'];
                        $request = $resource->getHttpRequest();
                        $config = $input['config'];
                        try {
                            $response = $this->client->send($request, $config);
                            return ['resource' => $resource, 'responseString' => Serializer::toString($response)];
                        } catch (\Exception $exception) {
                            $this->logger->error('Got Exception during sending the Request ' . $resource->getUrl());
                            $this->logger->error('Exception message: ' . $exception->getMessage());
                            return ['resource' => $resource, 'responseString' => Serializer::toString($response), 'exception' => $exception];
                        }
                    }
                )
            );
        foreach ($resources as $resource) {
            $identity = $this->identityRotator->switchIdentityFor($resource->getHttpRequest());
            $config = $this->client->prepareConfig($identity);
            $request = $resource->getHttpRequest();
            $this->logger->info('Loading resource from URL ' . $request->getUri());
            $this->logger->debug('Request config: ' . (empty($config) ? '<EMPTY>' : var_export(
                    array_map(function ($param) {
                        return ($param instanceof CookieJar) ? true : $param;
                    }, $config), true)));
            $this->eventDispatcher->dispatch(new RequestPrepared($request, $config));
            $wp->run(['resource' => $resource, 'config' => $config]);
        }
        $wp->waitForAllWorkers(); // wait for all workers
        foreach ($wp as $result) {

            try {
                extract($result['data']);
                /**
                 * @var HttpResource $resource
                 * @var string $responseString
                 * @var \Exception $exception
                 */
                $response = Serializer::fromString($responseString);
                if (empty($exception)) {
                    $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(), $response));
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
                } else {
                    if (null !== $response) {
                        $this->eventDispatcher->dispatch(new ResponseReceived($resource->getHttpRequest(), $response));
                    }
                    try {
                        $this->identityRotator->evaluateResult($identity, null);
                    } catch (\Exception $exception) {
                        $this->handleException($resource, $response, $exception);
                    }
                    $this->handleException($resource, $response, $exception);
                    $this->handleAnyway($resource, $response);

                }

            } catch (NoGatewaysLeftException $exception) {
                $this->handleException($resource, null, $exception);
                $this->shutdown();
            }
        }
    }
}