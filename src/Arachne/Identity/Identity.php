<?php

namespace Arachne\Identity;

use Arachne\Gateway\Gateway;

class Identity
{
    /**
     * @var string
     */
    private $userAgent;
    /**
     * @var string[]
     */
    private $defaultRequestHeaders;
    /**
     * @var bool
     */
    private $areCookiesEnabled;
    /**
     * @var bool
     */
    private $isJSEnabled;

    /**
     * @var
     */
    private $isSendReferer;

    private $gateway;

    public function __construct(
        Gateway $gateway,
        string $userAgent,
        array $defaultRequestHeaders = [],
        bool $areCookiesEnabled = true,
        bool $isJSEnabled = false,
        bool $isSendReferer = true
    ) {
        $this->gateway = $gateway;
        $this->userAgent = $userAgent;
        $this->defaultRequestHeaders = $defaultRequestHeaders;
        $this->areCookiesEnabled = $areCookiesEnabled;
        $this->isJSEnabled = $isJSEnabled;
        $this->isSendReferer = $isSendReferer;
    }

    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @return string
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @param string $userAgent
     */
    public function setUserAgent(string $userAgent)
    {
        $this->userAgent = $userAgent;
    }

    /**
     * @return \string[]
     */
    public function getDefaultRequestHeaders(): array
    {
        return $this->defaultRequestHeaders;
    }

    /**
     * @param \string[] $defaultRequestHeaders
     */
    public function setDefaultRequestHeaders(array $defaultRequestHeaders)
    {
        $this->defaultRequestHeaders = $defaultRequestHeaders;
    }

    /**
     * @return bool
     */
    public function areCookiesEnabled(): bool
    {
        return $this->areCookiesEnabled;
    }

    public function enableCookies()
    {
        $this->areCookiesEnabled = true;
        return $this;
    }

    public function disableCookies()
    {
        $this->areCookiesEnabled = false;
        return $this;
    }

    /**
     * @return bool
     */
    public function isJSEnabled(): bool
    {
        return $this->isJSEnabled;
    }

    /**
     */
    public function enableJS()
    {
        $this->isJSEnabled = true;
        return $this;
    }

    public function disableJS()
    {
        $this->isJSEnabled = false;
        return $this;
    }

    /**
     * @return mixed
     */
    public function isSendReferer()
    {
        return $this->isSendReferer;
    }

    /**
     */
    public function sendReferer()
    {
        $this->isSendReferer = true;
        return false;
    }

    public function skipReferer()
    {
        $this->isSendReferer = false;
        return $this;
    }

    public function __toString()
    {
        $data = [
            'User Agent' => $this->getUserAgent(),
            'Default Request Headers' => $this->getDefaultRequestHeaders(),
            'Enable Cookies?' => $this->areCookiesEnabled()? 'Yes' : 'No',
            'Enable JavaScript?' => $this->isJSEnabled()? 'Yes' : 'No',
            'Send Referer?' => $this->isSendReferer()? 'Yes' : 'No'
        ];
        return json_encode($data,  JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function isApplicableTo(string $url): bool
    {
        if (empty($this->gatewayProfile)) {
            return false;
        }
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
}