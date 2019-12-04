<?php

namespace Arachne\Client\Buzz;

class Curl extends \Buzz\Client\Curl
{
    protected function createHandle()
    {
        return curl_init();
    }
}
