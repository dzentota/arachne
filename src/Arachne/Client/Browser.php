<?php

namespace Arachne\Client;

use Arachne\Identity\Identity;
use Facebook\WebDriver\WebDriver;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * curl https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb -o ./chrome.deb
 * dpkg -i ./chrome.deb || apt-get install -yf
 * Class Browser
 * @package Arachne\Client
 */
class Browser implements ClientInterface
{
    private $driver;

    public function __construct(WebDriver $driver)
    {
        $this->driver = $driver;
    }

    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        $this->driver->get((string) $request->getUri());
        $response = new Response(200, [], $this->driver->getPageSource());
        $this->driver->close();
        return $response;
    }

    public function sendAsync(RequestInterface $request, array $options = [])
    {
        throw new \LogicException('Not supported');
    }

    public function prepareConfig(?Identity $identity = null): array
    {
        return [];
    }
}