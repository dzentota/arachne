<?php

namespace Arachne\Gateway;
use Arachne\Exceptions\GatewayException;
use Arachne\Gateway\Events\GatewayBlocked;
use Arachne\Gateway\Events\GatewayFailed;
use Arachne\Gateway\Events\GatewayRequested;
use Arachne\Gateway\Events\GatewaySucceeded;
use Arachne\Gateway\Events\GatewayUnblocked;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Gateway implements GatewayInterface
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var GatewayServer
     */
    private $gatewayServer;

    /**
     * @var
     */
    private $gatewayProfile;
    /**
     * @var int
     */
    private $currentTotalFails = 0;
    /**
     * @var int
     */
    private $maxTotalFails = -1;
    /**
     * @var int
     */
    private $currentConsecutiveFails = 0;
    /**
     * @var int
     */
    private $maxConsecutiveFails = 3;
    /**
     * @var bool
     */
    private $blocked = false;
    /**
     * @var int
     */
    private $totalRequests = 0;

    public function __construct(EventDispatcherInterface $eventDispatcher, GatewayServerInterface $gatewayServer, GatewayProfile $gatewayProfile = null)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->gatewayServer = $gatewayServer;
        $this->gatewayProfile = $gatewayProfile?? new GatewayProfile();

    }

    public function setGatewayServer(GatewayServer $gatewayServer)
    {
        $this->gatewayServer = $gatewayServer;
    }

    public function getGatewayProfile()
    {
        return $this->gatewayProfile;
    }

    public function isApplicableTo(RequestInterface $request)
    {
        $url = (string)$request->getUri();

        $whiteList = $this->gatewayProfile->getWhiteList();
        $blackList = $this->gatewayProfile->getBlackList();
        switch (true) {
            case !empty($whiteList) && !empty($blackList):
                $whiteListMatch = false;
                $blackListMatch = false;
                foreach ($whiteList as $regExp) {
                    if (preg_match("~$regExp~is", $url)) {
                        $whiteListMatch = true;
                        break;
                    }
                }
                foreach ($blackList as $regExp) {
                    if (preg_match("~$regExp~is", $url)) {
                        $blackListMatch = true;
                        break;
                    }
                }
                return $whiteListMatch && !$blackListMatch;
            case !empty($whiteList) && empty($blackList):
                foreach ($whiteList as $regExp) {
                    if (preg_match("~$regExp~is", $url)) {
                        return true;
                    }
                }
                return false;
            default:
                return false;
        }
    }

    /**
     * @param RequestInterface $request
     * @return bool
     */
    public function isUsableFor(RequestInterface $request) : bool
    {
        return ($this->isApplicableTo($request) && !$this->isBlocked() && !$this->hasTooManyFails());
    }

    /**
     * Call after a request failed
     * @return void
     */
    public function failed()
    {
        $this->currentTotalFails++;
        $this->currentConsecutiveFails++;
        $this->eventDispatcher->dispatch(new GatewayFailed($this->gatewayServer));
    }

    /**
     * Call after any request
     * @return void
     */
    public function requested()
    {
        $this->totalRequests++;
        $this->eventDispatcher->dispatch(new GatewayRequested($this->gatewayServer));
    }

    /**
     * Call after a request was successful
     * @return void
     */
    public function succeeded()
    {
        $this->currentConsecutiveFails = 0;
        $this->eventDispatcher->dispatch(new GatewaySucceeded($this->gatewayServer));
    }

    /**
     */
    public function block()
    {
        $this->blocked = true;
        $this->eventDispatcher->dispatch(new GatewayBlocked($this->gatewayServer));
        throw new GatewayException(sprintf('Gateway %s blocked', $this->gatewayServer));
    }

    /**
     */
    public function unblock()
    {
        $this->blocked = false;
        $this->eventDispatcher->dispatch(new GatewayUnblocked($this->gatewayServer));
    }

    /**
     * @return bool
     */
    public function hasTooManyFails() : bool
    {
        return ($this->hasTooManyConsecutiveFails() || $this->hasTooManyTotalFails());
    }

    /**
     * @return bool
     */
    public function hasTooManyConsecutiveFails() : bool
    {
        return $this->maxConsecutiveFails > -1 && $this->currentConsecutiveFails >= $this->maxConsecutiveFails;
    }

    /**
     * @return bool
     */
    public function hasTooManyTotalFails() : bool
    {
        return $this->maxTotalFails > -1 && $this->currentTotalFails >= $this->maxTotalFails;
    }


    /**
     * @return boolean
     */
    public function isBlocked() : bool
    {
        return $this->blocked;
    }

    /**
     * @return int
     */
    public function getCurrentConsecutiveFails() : int
    {
        return $this->currentConsecutiveFails;
    }

    /**
     * @param int $currentConsecutiveFails
     * @return $this
     */
    public function setCurrentConsecutiveFails(int $currentConsecutiveFails)
    {
        $this->currentConsecutiveFails = $currentConsecutiveFails;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentTotalFails() : int
    {
        return $this->currentTotalFails;
    }

    /**
     * @param mixed $currentTotalFails
     * @return $this
     */
    public function setCurrentTotalFails($currentTotalFails)
    {
        $this->currentTotalFails = $currentTotalFails;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxConsecutiveFails() : int
    {
        return $this->maxConsecutiveFails;
    }

    /**
     * @param int $maxConsecutiveFails
     * @return $this
     */
    public function setMaxConsecutiveFails(int $maxConsecutiveFails)
    {
        $this->maxConsecutiveFails = $maxConsecutiveFails;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxTotalFails() : int
    {
        return $this->maxTotalFails;
    }

    /**
     * @param int $maxTotalFails
     * @return $this
     */
    public function setMaxTotalFails(int $maxTotalFails)
    {
        $this->maxTotalFails = $maxTotalFails;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalRequests() : int
    {
        return $this->totalRequests;
    }

    /**
     * @param int $totalRequests
     * @return $this
     */
    public function setTotalRequests(int $totalRequests)
    {
        $this->totalRequests = $totalRequests;
        return $this;
    }

    /**
     * @return GatewayServerInterface
     */
    public function getGatewayServer(): GatewayServerInterface
    {
        return $this->gatewayServer;
    }

}
