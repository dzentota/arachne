<?php

namespace Arachne\Gateway;

use Psr\Http\Message\RequestInterface;

/**
 * Interface GatewayInterface
 * @package Arachne\Gateway
 */
interface GatewayInterface
{
    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function isUsableFor(RequestInterface $request) : bool;
    /**
     * Call after a request failed
     * @return void
     */
    public function failed();
    /**
     * Call after any request
     * @return void
     */
    public function requested();
    /**
     * Call afer a request was successful
     * @return void
     */
    public function succeeded();
    /**
     */
    public function block();
    /**
     */
    public function unblock();
    /**
     * @return GatewayServer
     */
    public function getGatewayServer() : GatewayServer ;

}