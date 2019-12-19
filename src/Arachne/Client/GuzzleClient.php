<?php

namespace Arachne\Client;

use Arachne\Client\Events\ResponseReceived;
use Arachne\Gateway\Localhost;
use Arachne\Identity\IdentityRotatorInterface;
use GuzzleHttp\ClientInterface as GuzzleInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Arachne\Exceptions\HttpRequestException;
use Arachne\Identity\Identity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * curl.cainfo="/path/to/downloaded/cacert.pem"
 *
 * Class GuzzleClient
 * @package Arachne\Client
 *
 *    $config = [
 *       'allow_redirects' => [
 *           'max' => 5,
 *           'protocols' => ['http', 'https'],
 *           'strict' => false,
 *       ],
 *       'http_errors' => true,
 *       'decode_content' => true,
 *       'verify' => true,
 *       'connect_timeout' => 3.14,
 *       'timeout' => 3.14
 *   ];
 *
 */
class GuzzleClient extends GenericClient
{
    /**
     * @var GuzzleInterface
     */
    private $httpClient;

    /**
     * GuzzleClient constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param IdentityRotatorInterface $identityRotator
     * @param GuzzleInterface|null $client
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        IdentityRotatorInterface $identityRotator,
        GuzzleInterface $client = null
    ) {
        parent::__construct($eventDispatcher, $identityRotator);
        $this->httpClient = $client;
    }

    /**
     * @return \GuzzleHttp\Client|GuzzleInterface|null
     */
    public function getHttpClient()
    {
        return $this->httpClient ?? new \GuzzleHttp\Client(
                [
                    'cookies' => true,
                    'http_errors' => false,
//                'verify' => __DIR__ . '/cacert.pem'
                    'verify' => false,
                ]);
    }

    /**
     * @param GuzzleInterface $httpClient
     * @return $this
     */
    public function setHttpClient(GuzzleInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        return $this;
    }

    /**
     * @param array $requestConfig
     * @param $identity
     * @return mixed
     */
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
        $config['cookies'] = $requestConfig['cookies'] ?? ($identity ? $identity->areCookiesEnabled() : true);
        return $config;
    }

    /**
     * @param $identity
     */
    public function ensureIdentityIsCompatibleWithClient(?Identity $identity)
    {
        if ($identity === null) {
            return;
        }
        if ($identity->isJSEnabled()) {
            throw new \LogicException('Guzzle client does NOT support JS');
        }
    }

    /**
     * @param RequestInterface $request
     * @param array $config
     * @return ResponseInterface
     * @throws HttpRequestException
     */
    protected function sendHTTPRequest(
        RequestInterface $request,
        array $config
    ): ResponseInterface {
        try {
            $defaultConfig = $this->httpClient->getConfig();
            $requestConfig = array_merge($defaultConfig, $config);
            //CookieJar can not be converted to string and passed to worker from parent process
            if (!empty($requestConfig['cookies'])) {
                $requestConfig['cookies'] = new CookieJar();
            }
            $httpClient = $this->getHttpClient();
            $response = $httpClient
                ->send($request, $requestConfig);
        } catch (RequestException $exception) {
            if ($exception->hasResponse()) {
                $this->eventDispatcher->dispatch(ResponseReceived::name,
                    new ResponseReceived($request, $exception->getResponse()));
            }
            throw new HttpRequestException('Failed to send HTTP Request', 0, $exception);
        }
        return $response;
    }
}
