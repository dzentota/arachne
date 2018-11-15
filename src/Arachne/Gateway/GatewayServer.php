<?php

namespace Arachne\Gateway;
use Respect\Validation\Validator as v;

class GatewayServer implements GatewayServerInterface
{
    private $type;
    private $ip;
    private $port;
    private $username;
    private $password;

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getIp() : string
    {
        return $this->ip;
    }

    /**
     * @return int
     */
    public function getPort() : int
    {
        return $this->port;
    }

    /**
     * @return null|string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return null|string
     */
    public function getPassword()
    {
        return $this->password;
    }


    private function __construct($ip, $port, $type = 'http', $username = null, $password = null)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->type = $type;
        $this->username = $username;
        $this->password = $password;
    }

    public static function fromString(string $proxy)
    {
        $data = parse_url($proxy);
        $ip = $data['host']?? null;//@todo rename ip to host
        if (empty($ip)) {//e.g  $proxy = '127.0.0.1'
            $ip = $data['path']?? null;
        }
        v::notEmpty()
            //->ip()
            ->assert($ip);

        $port = $data['port']?? 80;
        v::intVal()->assert($port);

        $type = $data['scheme']?? 'http';
        v::in(['http', 'https', 'tcp', 'socks5', 'socks4', 'socks4a'])->assert($type);

        $username = $data['user']?? null;
        $password = $data['pass']?? null;

        return new self($ip, $port, $type, $username, $password);
    }

    public static function localhost()
    {
        return new Localhost();
    }

    public function __toString()
    {
        return empty($this->username)?
            sprintf('%s://%s:%s', $this->type, $this->ip, $this->port) :
            sprintf('%s://%s:%s@%s:%s', $this->type, $this->username, $this->password, $this->ip, $this->port);
    }

}