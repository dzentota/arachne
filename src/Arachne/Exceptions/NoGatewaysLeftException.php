<?php

namespace Arachne\Exceptions;

class NoGatewaysLeftException extends GatewayException
{
    protected $message = 'No gateways left';
}