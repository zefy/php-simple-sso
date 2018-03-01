<?php

namespace Zefy\SimpleSSO;

use Zefy\SimpleSSO\Interfaces\SSOBrokerInterface;

/**
 * Class SSOBroker. This class is only a skeleton.
 * First of all, you need to implement abstract functions in your own class.
 * Secondly, you should create a page which will be your SSO server.
 *
 * @package Zefy\SimpleSSO
 */
abstract class SSOBroker implements SSOBrokerInterface
{
    /**
     * SSO server url.
     *
     * @var string
     */
    protected $ssoServerUrl;

    /**
     * Broker name.
     *
     * @var string
     */
    protected $brokerName;

    /**
     * Broker secret token.
     *
     * @var string
     */
    protected $brokerSecret;

    /**
     * User info retrieved from the SSO server.
     *
     * @var array
     */
    protected $userInfo;

    /**
     * Random token generated for the client and broker.
     *
     * @var string|null
     */
    protected $token;


    public function __construct()
    {
        $this->setOptions();
        $this->saveToken();
    }

    /**
     * Attach client session to broker session in SSO server.
     *
     * @return void
     */
    public function attach()
    {
        $parameters = [
            'return_url' => $this->getCurrentUrl(),
            'broker' => $this->brokerName,
            'token' => $this->token,
            'checksum' => hash('sha256', 'attach' . $this->token . $this->brokerSecret)
        ];

        $attachUrl = $this->generateCommandUrl('attach', $parameters);

        $this->redirect($attachUrl);
    }

    /**
     * Getting user info from SSO based on client session.
     *
     * @return array
     */
    public function getUserInfo()
    {
        if (!isset($this->userInfo) || !$this->userInfo) {
            $this->userInfo = $this->makeRequest('GET', 'userInfo');
        }

        return $this->userInfo;
    }

    /**
     * Login client to SSO server with user credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function login(string $username, string $password)
    {
        $this->userInfo = $this->makeRequest('POST', 'login', compact('username', 'password'));

        if (!isset($this->userInfo['error']) && isset($this->userInfo['data']['id'])) {
            return true;
        }

        return false;
    }

    /**
     * Logout client from SSO server.
     *
     * @return void
     */
    public function logout()
    {
        $this->makeRequest('POST', 'logout');
    }

    /**
     * Generate request url.
     *
     * @param string $command
     * @param array $parameters
     *
     * @return string
     */
    protected function generateCommandUrl(string $command, array $parameters = [])
    {
        $query = '';
        if (!empty($parameters)) {
            $query = '?' . http_build_query($parameters);
        }

        return $this->ssoServerUrl . '/sso/' . $command . $query;
    }

    /**
     * Generate session key with broker name, broker secret and unique client token.
     *
     * @return string
     */
    protected function getSessionId()
    {
        $checksum = hash('sha256', 'session' . $this->token . $this->brokerSecret);
        return "SSO-{$this->brokerName}-{$this->token}-$checksum";
    }

    /**
     * Set base class options (sso server url, broker name and secret, etc).
     *
     * @return void
     */
    abstract protected function setOptions();

    /**
     * Somehow save random token for client.
     *
     * @return void
     */
    abstract protected function saveToken();

    /**
     * Delete saved token.
     *
     * @return void
     */
    abstract protected function deleteToken();

    /**
     * Make request to SSO server.
     *
     * @param string $method Request method 'post' or 'get'.
     * @param string $command Request command name.
     * @param array $parameters Parameters for URL query string if GET request and form parameters if it's POST request.
     *
     * @return array
     */
    abstract protected function makeRequest(string $method, string $command, array $parameters = []);

    /**
     * Redirect client to specified url.
     *
     * @param string $url URL to be redirected.
     * @param array $parameters HTTP query string.
     * @param int $httpResponseCode HTTP response code for redirection.
     *
     * @return void
     */
    abstract protected function redirect(string $url, array $parameters = [], int $httpResponseCode = 307);

    /**
     * Getting current url which can be used as return to url.
     *
     * @return string
     */
    abstract protected function getCurrentUrl();
}
