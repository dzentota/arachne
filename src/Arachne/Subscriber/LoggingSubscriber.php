<?php

declare(strict_types=1);

namespace Arachne\Subscriber;

use Arachne\Client\Events\RequestPrepared;
use Arachne\Client\Events\ResponseReceived;
use Arachne\Event\EventSummaryInterface;
use Arachne\Gateway\Events\GatewayBlocked;
use Arachne\Gateway\Events\GatewayFailed;
use Arachne\Gateway\Events\GatewayRequested;
use Arachne\Gateway\Events\GatewaySucceeded;
use Arachne\Gateway\Events\GatewayUnblocked;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LoggingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {

    }

    public static function getSubscribedEvents(): array
    {
        return array(
            GatewayBlocked::class => 'logGatewayEvent',
            GatewayFailed::class => 'logGatewayEvent',
            GatewayRequested::class => 'logGatewayEvent',
            GatewaySucceeded::class => 'logGatewayEvent',
            GatewayUnblocked::class => 'logGatewayEvent',
            RequestPrepared::class => 'logRequest',
            ResponseReceived::class => 'logResponse'
        );
    }

    public function logGatewayEvent(EventSummaryInterface $event)
    {
        $this->logger->debug($event->getSummary());
    }

    public function logRequest(RequestPrepared $event): void
    {
        $this->logger->info('Loading resource from URL ' . $event->getRequest()->getUri());
        $config = $event->getConfig();
        $this->logger->debug('Request config: ' . (empty($config) ? '<EMPTY>' : var_export(
                array_map(static fn($param) => ($param instanceof CookieJar) ? true : $param, $config), true))
        );
    }

    public function logResponse(ResponseReceived $event): void
    {
        $this->logger->info('Got response from URL ' . $event->getRequest()->getUri());
        $this->logger->debug('HTTP Status: ' . $event->getResponse()->getStatusCode());
    }
}