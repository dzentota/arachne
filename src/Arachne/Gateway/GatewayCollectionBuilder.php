<?php

namespace Arachne\Gateway;

use Arachne\Exceptions\Exception;
use Arachne\Identity\IdentitiesCollection;
use Arachne\Interval\ConstTimeInterval;
use Arachne\Interval\NullCounter;
use Arachne\Interval\NullTimeInterval;
use Arachne\Interval\NumberCounter;
use Arachne\Interval\RandomCounterInterval;
use Arachne\Identity\Identity;
use Arachne\Interval\RandomTimeInterval;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class GatewayCollectionBuilder
{
    /**
     * @var bool
     */
    private $useOwnIp = true;

    /**
     * @var string[]
     */
    private $proxyServers;

    /**
     * @var callable
     */
    private $evaluationFunction = null;

    /**
     * @var int
     */
    private $maxConsecutiveFails = null;

    /**
     * @var int
     */
    private $maxTotalFails = null;

    /**
     * @var int
     */
    private $from;

    /**
     * @var int
     */
    private $to;

    /**
     * @var IdentitiesCollection
     */
    private $identities;

    /**
     * @var int
     */
    private $fromRequest;

    /**
     * @var int
     */
    private $toRequest;

    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return $this
     */
    public function skipOwnIp()
    {
        $this->useOwnIp = false;
        return $this;
    }

    /**
     * Expects an array of proxy strings as input, e.g.
     * ["217.0.0.8:8080", "foo@bar:125.12.2.1:7777", "28.3.6.1"]
     * Each proxy string is used to create a new Gateway
     * @param string[] $stringProxies
     * @return $this
     */
    public function withProxiesFromArray(array $stringProxies)
    {
        foreach ($stringProxies as $proxy) {
            if (!$proxy instanceof GatewayServer) {
                $this->proxyServers[] = GatewayServer::fromString($proxy);
            } else {
                $this->proxyServers[] = $proxy;
            }
        }
        return $this;
    }

    /**
     * Expects a seperated string of proxies as input, e.g.
     * "217.0.0.8:8080, foo@bar:125.12.2.1:7777, 28.3.6.1"
     * The separator can be defined by the $separator argument, it defaults to ",".
     * the string is split on the $separator and each element is trimmed to get the plain proxy string.
     * @param string $proxyString
     * @param string $separator [optional]. Default: ",";
     * @return GatewayCollectionBuilder
     */
    public function withProxiesFromString(string $proxyString, string $separator = ",")
    {
        $ps = mb_split($separator, $proxyString);
        $proxies = [];
        foreach ($ps as $p) {
            $proxy = trim($p);
            if ($proxy != "") {
                $proxies[] = $proxy;
            }
        }
        return $this->withProxiesFromArray($proxies);
    }

    /**
     * @param $filename
     * @param string $separator
     * @return GatewayCollectionBuilder
     */
    public function withProxiesFromFile(string $filename, string $separator = ",")
    {
        if (is_readable($filename)) {
            return $this->withProxiesFromString(file_get_contents($filename), $separator);
        } else {
            throw new \InvalidArgumentException("$filename file does not exist or is not readable");
        }
    }

    /**
     * @param callable $evaluationFunction
     * @return $this
     */
    public function evaluatesGatewayResultsBy(callable $evaluationFunction)
    {
        $this->evaluationFunction = $evaluationFunction;
        return $this;
    }

    /**
     * @return $this
     */
    public function evaluatesGatewayResultsByDefault()
    {
        $this->evaluationFunction = null;
        return $this;
    }

    /**
     * @param int $maxTotalFails
     * @return $this
     */
    public function eachGatewayMayFailInTotal(int $maxTotalFails)
    {
        $this->maxTotalFails = $maxTotalFails;
        return $this;
    }

    /**
     * @return $this
     */
    public function eachGatewayMayFailInfinitelyInTotal()
    {
        $this->maxTotalFails = -1;
        return $this;
    }

    /**
     * @param int $maxConsecutiveFails
     * @return $this
     */
    public function eachGatewayMayFailConsecutively(int $maxConsecutiveFails)
    {
        $this->maxConsecutiveFails = $maxConsecutiveFails;
        return $this;
    }

    /**
     * @return $this
     */
    public function eachGatewayMayFailInfinitelyConsecutively()
    {
        $this->maxConsecutiveFails = -1;
        return $this;
    }

    /**
     * @param int $from
     * @param int|null $to
     * @return $this
     */
    public function eachGatewayNeedsToWaitBetweenRequests(int $from, int $to = null)
    {
        $this->from = $from;
        $this->to = $to;
        return $this;
    }

    /**
     * @return $this
     */
    public function proxiesDontNeedToWait()
    {
        $this->from = null;
        $this->to = null;
        return $this;
    }

    /**
     * @param IdentitiesCollection $identities
     * @return $this
     */
    public function distributeIdentitiesAmongProxies(IdentitiesCollection $identities)
    {
        $this->identities = $identities;
        return $this;
    }

    /**
     * @param int $nrOfIdentitiesPerGateway
     * @param string[] $userAgentSeed
     * @param string[] $requestHeaderSeed
     * @param bool[] $areCookiesEnabledSeed
     * @param bool[] $isJsEnabledSeed
     * @param bool[] $isSendRefererSeed
     * @return $this
     */
    public function generateIdentitiesForProxies(
        $nrOfIdentitiesPerGateway = 2,
        array $userAgentSeed = [],
        array $requestHeaderSeed = [],
        array $areCookiesEnabledSeed = [],
        array $isJsEnabledSeed = [],
        array $isSendRefererSeed = []
    ) {
        $proxies = count($this->proxyServers) + ($this->useOwnIp? 1 : 0);

        $targetIdentityCount = $nrOfIdentitiesPerGateway * $proxies;

        if (count($userAgentSeed) < $nrOfIdentitiesPerGateway) {
            $numberOfUaNeeded = $nrOfIdentitiesPerGateway - count($userAgentSeed);
            for ($n = 0; $n < $numberOfUaNeeded; $n++) {
                $userAgentSeed[] = \Campo\UserAgent::random();
            }
        }
        $identities = [];

        for ($i = 0; $i < $targetIdentityCount; $i++) {
            $uaKey = array_rand($userAgentSeed);
            $ua = $userAgentSeed[$uaKey];
            if (count($areCookiesEnabledSeed)) {
                $cookiesKey = array_rand($areCookiesEnabledSeed);
                $areCookiesEnabled = $areCookiesEnabledSeed[$cookiesKey];
            } else {
                $areCookiesEnabled = true;
            }
            if (count($isJsEnabledSeed)) {
                $jsKey = array_rand($isJsEnabledSeed);
                $isJsEnabled = $isJsEnabledSeed[$jsKey];
            } else {
                $isJsEnabled = false;
            }
            if (count($isSendRefererSeed)) {
                $sendRefererKey = array_rand($isSendRefererSeed);
                $isSendReferer = $isSendRefererSeed[$sendRefererKey];
            } else {
                $isSendReferer = true;
            }
            if (!empty($requestHeaderSeed)) {
                $headersKey = array_rand($requestHeaderSeed);
                $headers = $requestHeaderSeed[$headersKey];
            } else {
                $headers = [];
            }
            $identities[] = new Identity($ua, $headers, $areCookiesEnabled, $isJsEnabled, $isSendReferer);
        }
        $this->identities = new IdentitiesCollection(...$identities);
        return $this;
    }

    /**
     * @return $this
     */
    public function eachGatewaySwitchesIdentityAfterEachRequest()
    {
        $this->fromRequest = null;
        $this->toRequest = null;
        return $this;
    }

    /**
     * @param int $from
     * @param int $to
     * @return $this
     */
    public function eachGatewaySwitchesIdentityAfterRequests($from, $to = null)
    {
        $this->fromRequest = $from;
        $this->toRequest = $to;
        return $this;
    }

    /**
     * @return GatewaysCollection
     * @throws Exception
     */
    public function build()
    {
        $proxies = [];
        $proxyServers = $this->proxyServers;
        if ($this->useOwnIp) {
            $proxyServers[] = 'localhost';
        }
        $numberOfIpAddresses = count($proxyServers);
        if (count($this->identities) < $numberOfIpAddresses) {
            $this->generateIdentitiesForProxies();
        }
        if (!empty($proxyServers)) {
            $identitySlice = floor(count($this->identities) / $numberOfIpAddresses);
            $rest = count($this->identities) % $numberOfIpAddresses;
            foreach ($proxyServers as $proxyServer) {
                $time = new NullTimeInterval();
                if ($this->from !== null && $this->to !== null) {
                    $time = new RandomTimeInterval($this->from, $this->to);
                }
                if ($this->from !== null && $this->to === null) {
                    $time = new ConstTimeInterval($this->from);
                }
                $counter = new NullCounter();
                if ($this->fromRequest !== null && $this->toRequest !== null) {
                    $counter = new RandomCounterInterval($this->fromRequest, $this->toRequest);
                }
                if ($this->fromRequest !== null && $this->toRequest === null) {
                    $counter = new NumberCounter($this->fromRequest);
                }
                $slice = $identitySlice;
                if ($rest > 0) { // if we still got a rest from the division, we can add an additional identity
                    $rest--;
                    $slice++;
                }
                $identities = $this->identities->slice(0, $slice);
                $proxy = ($proxyServer === 'localhost'? new Localhost($this->eventDispatcher, $identities)
                    : new Gateway($this->eventDispatcher, $proxyServer, $identities))
                    ->setMaxConsecutiveFails($this->maxConsecutiveFails?? 5)
                    ->setMaxTotalFails($this->maxTotalFails?? -1)
                    ->setWaitInterval($time)
                    ->setCountInterval($counter);
                if (isset($this->evaluationFunction)) {
                    $proxy->setEvaluationFunction($this->evaluationFunction);
                }
                $proxies[] = $proxy;
            }
        }
        if (empty($proxies)) {
            throw new Exception('No proxies specified');
        }
        return new GatewaysCollection(...$proxies);
    }

}
