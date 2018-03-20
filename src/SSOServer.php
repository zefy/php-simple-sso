<?php

namespace Zefy\SimpleSSO;

use Zefy\SimpleSSO\Exceptions\SSOServerException;
use Zefy\SimpleSSO\Interfaces\SSOServerInterface;

/**
 * Class SSOServer. This class is only a skeleton.
 * First of all, you need to implement abstract functions.
 *
 * @package Zefy\SimpleSSO
 */
abstract class SSOServer implements SSOServerInterface
{
    /**
     * @var mixed
     */
    protected $brokerId;

    /**
     * Attach user's session to broker's session.
     *
     * @param string|null $broker Broker's name/id.
     * @param string|null $token Token sent from broker.
     * @param string|null $checksum Calculated broker+token checksum.
     *
     * @return string or redirect
     */
    public function attach(?string $broker, ?string $token, ?string $checksum)
    {
        try {
            if (!$broker) {
                $this->fail('No broker id specified.', true);
            }

            if (!$token) {
                $this->fail('No token specified.', true);
            }

            if (!$checksum || $checksum != $this->generateAttachChecksum($broker, $token)) {
                $this->fail('Invalid checksum.', true);
            }

            $this->startUserSession();
            $sessionId = $this->generateSessionId($broker, $token);

            $this->saveBrokerSessionData($sessionId, $this->getSessionData('id'));
        } catch (SSOServerException $e) {
            return $this->redirect(null, ['sso_error' => $e->getMessage()]);
        }

        $this->attachSuccess();
    }

    /**
     * @param null|string $username
     * @param null|string $password
     *
     * @return string
     */
    public function login(?string $username, ?string $password)
    {
        try {
            $this->startBrokerSession();

            if (!$username || !$password) {
                $this->fail('No username and/or password provided.');
            }

            if (!$this->authenticate($username, $password)) {
                $this->fail('User authentication failed.');
            }
        } catch (SSOServerException $e) {
            return $this->returnJson(['error' => $e->getMessage()]);
        }

        $this->setSessionData('sso_user', $username);

        return $this->userInfo();
    }

    /**
     * Logging user out.
     *
     * @return string
     */
    public function logout()
    {
        try {
            $this->startBrokerSession();
            $this->setSessionData('sso_user', null);
        } catch (SSOServerException $e) {
            return $this->returnJson(['error' => $e->getMessage()]);
        }

        return $this->returnJson(['success' => 'User has been successfully logged out.']);
    }

    /**
     * Returning user info for the broker.
     *
     * @return string
     */
    public function userInfo()
    {
        try {
            $this->startBrokerSession();

            $username = $this->getSessionData('sso_user');

            if (!$username) {
                $this->fail('User not authenticated. Session ID: ' . $this->getSessionData('id'));
            }

            if (!$user = $this->getUserInfo($username)) {
                $this->fail('User not found.');
            }
        } catch (SSOServerException $e) {
            return $this->returnJson(['error' => $e->getMessage()]);
        }

        return $this->returnUserInfo($user);
    }

    /**
     * Resume broker session if saved session id exist.
     *
     * @throws SSOServerException
     *
     * @return void
     */
    protected function startBrokerSession()
    {
        if (isset($this->brokerId)) {
            return;
        }

        $sessionId = $this->getBrokerSessionId();

        if (!$sessionId) {
            $this->fail('Missing session key from broker.');
        }

        $savedSessionId = $this->getBrokerSessionData($sessionId);

        if (!$savedSessionId) {
            $this->fail('There is no saved session data associated with the broker session id.');
        }

        $this->startSession($savedSessionId);

        $this->brokerId = $this->validateBrokerSessionId($sessionId);
    }

    /**
     * Check if broker session is valid.
     *
     * @param string $sessionId Session id from the broker.
     *
     * @throws SSOServerException
     *
     * @return string
     */
    protected function validateBrokerSessionId(string $sessionId)
    {
        $matches = null;

        if (!preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $this->getBrokerSessionId(), $matches)) {
            $this->fail('Invalid session id');
        }

        if ($this->generateSessionId($matches[1], $matches[2]) != $sessionId) {
            $this->fail('Checksum failed: Client IP address may have changed');
        }

        return $matches[1];
    }

    /**
     * Generate session id from session token.
     *
     * @param string $brokerId
     * @param string $token
     *
     * @throws SSOServerException
     *
     * @return string
     */
    protected function generateSessionId(string $brokerId, string $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!$broker) {
            $this->fail('Provided broker does not exist.');
        }

        return 'SSO-' . $brokerId . '-' . $token . '-' . hash('sha256', 'session' . $token . $broker['secret']);
    }

    /**
     * Generate session id from session token.
     *
     * @param string $brokerId
     * @param string $token
     *
     * @throws SSOServerException
     *
     * @return string
     */
    protected function generateAttachChecksum($brokerId, $token)
    {
        $broker = $this->getBrokerInfo($brokerId);

        if (!$broker) {
            $this->fail('Provided broker does not exist.');
        }

        return hash('sha256', 'attach' . $token . $broker['secret']);
    }

    /**
     * Do things if attaching was successful.
     *
     * @return void
     */
    protected function attachSuccess()
    {
        $this->redirect();
    }

    /**
     * If something failed, throw an Exception or redirect.
     *
     * @param null|string $message
     * @param bool $isRedirect
     * @param null|string $url
     *
     * @throws SSOServerException
     *
     * @return void
     */
    protected function fail(?string $message, bool $isRedirect = false, ?string $url = null)
    {
        if (!$isRedirect) {
            throw new SSOServerException($message);
        }

        $this->redirect($url, ['sso_error' => $message]);
    }

    /**
     * Redirect to provided URL with query string.
     *
     * If $url is null, redirect to url which given in 'return_url'.
     *
     * @param string|null $url URL to be redirected.
     * @param array $parameters HTTP query string.
     * @param int $httpResponseCode HTTP response code for redirection.
     *
     * @return mixed
     */
    abstract protected function redirect(?string $url = null, array $parameters = [], int $httpResponseCode = 307);

    /**
     * Returning json response for the broker.
     *
     * @param null|array $response Response array which will be encoded to json.
     * @param int $httpResponseCode HTTP response code.
     *
     * @return string
     */
    abstract protected function returnJson(?array $response = null, int $httpResponseCode = 204);

    /**
     * Authenticate using user credentials
     *
     * @param string $username
     * @param string $password
     *
     * @return bool|array
     */
    abstract protected function authenticate(string $username, string $password);

    /**
     * Get the secret key and other info of a broker
     *
     * @param string $brokerId
     *
     * @return null|array
     */
    abstract protected function getBrokerInfo(string $brokerId);

    /**
     * Get the information about a user
     *
     * @param string $username
     *
     * @return array|object|null
     */
    abstract protected function getUserInfo(string $username);

    /**
     * Returning user info for broker. Should return json or something like that.
     *
     * @param array|object $user Can be user object or array.
     *
     * @return mixed
     */
    abstract protected function returnUserInfo($user);

    /**
     * Return session id sent from broker.
     *
     * @return null|string
     */
    abstract protected function getBrokerSessionId();

    /**
     * Start new session when user visits server.
     *
     * @return void
     */
    abstract protected function startUserSession();

    /**
     * Set session data
     *
     * @param string $key
     * @param null|string $value
     *
     * @return void
     */
    abstract protected function setSessionData(string $key, ?string $value = null);

    /**
     * Get data saved in session.
     *
     * @param string $key
     *
     * @return null|string
     */
    abstract protected function getSessionData(string $key);

    /**
     * Start new session with specific session id.
     *
     * @param $sessionId
     *
     * @return void
     */
    abstract protected function startSession(string $sessionId);

    /**
     * Save broker session data to cache.
     *
     * @param string $brokerSessionId
     * @param string $sessionData
     *
     * @return void
     */
    abstract protected function saveBrokerSessionData(string $brokerSessionId, string $sessionData);

    /**
     * Get broker session data from cache.
     *
     * @param string $brokerSessionId
     *
     * @return null|string
     */
    abstract protected function getBrokerSessionData(string $brokerSessionId);
}
