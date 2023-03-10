<?php
declare(strict_types=1);

namespace Arachne\Client;

use Arachne\Identity\Identity;
use GuzzleHttp\Psr7\Response;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Communication\Message;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Chrome implements ClientInterface
{
    public function __construct(private BrowserFactory $browserFactory)
    {
    }

    /**
     * @param RequestInterface $request
     * @param array $options
     * @return ResponseInterface
     * @throws \HeadlessChromium\Exception\CommunicationException
     * @throws \HeadlessChromium\Exception\CommunicationException\CannotReadResponse
     * @throws \HeadlessChromium\Exception\CommunicationException\InvalidResponse
     * @throws \HeadlessChromium\Exception\CommunicationException\ResponseHasError
     * @throws \HeadlessChromium\Exception\NavigationExpired
     * @throws \HeadlessChromium\Exception\NoResponseAvailable
     * @throws \HeadlessChromium\Exception\OperationTimedOut
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        //@todo add cache based on $options
        /**
         * use \HeadlessChromium\Exception\BrowserConnectionFailed;

        // path to the file to store websocket's uri
        $socket = \file_get_contents('/tmp/chrome-php-demo-socket');

        try {
        $browser = BrowserFactory::connectToBrowser($socket);
        } catch (BrowserConnectionFailed $e) {
        // The browser was probably closed, start it again
        $factory = new BrowserFactory();
        $browser = $factory->createBrowser([
        'keepAlive' => true,
        ]);

        // save the uri to be able to connect again to browser
        \file_put_contents($socketFile, $browser->getSocketUri(), LOCK_EX);
        }
         */
        $browser = $this->browserFactory->createBrowser($options);
        $page = $browser->createPage();

        $statusCode = 500;

        $responseHeaders = [];

        $page->getSession()->once(
            "method:Network.responseReceived",
            function ($params) use (& $statusCode, & $responseHeaders) {
                $statusCode = $params['response']['status'];
                $responseHeaders = $this->sanitizeResponseHeaders($params['response']['headers']);
            }
        );

        $content = '';
        $page->getSession()->once(
            'method:Network.loadingFinished',
            function (array $params) use ($page, &$content): void {
                $request_id = $params["requestId"]?? null;
                $data = $page->getSession()->sendMessageSync(
                    new Message('Network.getResponseBody',
                    ['requestId' => $request_id])
                )->getData();
                $content = $data["result"]["body"]?? '';
            });

        $page->navigate($request->getUri()->__toString())
            ->waitForNavigation();
        return new Response($statusCode, $responseHeaders, $content);
    }

    public function sendAsync(RequestInterface $request, array $options = [])
    {
        // TODO: Implement sendAsync() method.
    }

    public function prepareConfig(?Identity $identity = null): array
    {
        $options = [
            'windowSize' => [1920, 1000],
            'enableImages' => false,
        ];
        $options['userAgent'] = $identity->getUserAgent();
        return $options;
    }

    /**
     * @param string[] $headers
     * @return string[]
     */
    protected function sanitizeResponseHeaders(array $headers): array
    {
        foreach ($headers as $key => $value) {
            $headers[$key] = explode(PHP_EOL, $value)[0];
        }

        return $headers;
    }

}