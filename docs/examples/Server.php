<?php

use Zefy\SimpleSSO\SSOServer;

class Server extends SSOServer
{
    /**
     * All available brokers list.
     *
     * @var array
     */
    protected $brokers = [
        'broker1' => [
            'secret' => 's3cr3th4sh',
        ],
    ];

    /**
     * All available users.
     *
     * @var array
     */
    protected $users = [
        'user1' => [
            'password1'
        ],
    ];

    /**
     * Redirect to provided URL with query string.
     *
     * If $url is null, redirect to url which given in 'return_url'.
     *
     * @param string|null $url URL to be redirected.
     * @param array $parameters HTTP query string.
     * @param int $httpResponseCode HTTP response code for redirection.
     *
     * @return void
     */
    protected function redirect(?string $url = null, array $parameters = [], int $httpResponseCode = 307)
    {
        if (!$url) {
            $url = urldecode($_GET['return_url']);
        }
        $query = '';
        // Making URL query string if parameters given.
        if (!empty($parameters)) {
            $query = '?';
            if (parse_url($url, PHP_URL_QUERY)) {
                $query = '&';
            }
            $query .= http_build_query($parameters);
        }
        header('Location: ' . $url . $query);
        exit;
    }

    /**
     * Returning json response for the broker.
     *
     * @param null|array $response Response array which will be encoded to json.
     * @param int $httpResponseCode HTTP response code.
     *
     * @return string
     */
    protected function returnJson(?array $response = null, int $httpResponseCode = 200)
    {
        header('Content-Type: application/json');
        return json_encode($response);
    }

    /**
     * Authenticate using user credentials
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function authenticate(string $username, string $password)
    {
        if (!isset($this->users[$username]) || $this->users[$username]['password'] != $password) {
            return false;
        }

        return true;
    }

    /**
     * Get the secret key and other info of a broker
     *
     * @param string $brokerId
     *
     * @return null|array
     */
    protected function getBrokerInfo(string $brokerId)
    {
        if (!isset($this->brokers[$brokerId])) {
            return null;
        }

        return $this->brokers[$brokerId];
    }

    /**
     * Get the information about a user
     *
     * @param string $username
     *
     * @return array|object|null
     */
    protected function getUserInfo(string $username)
    {
        if (!isset($this->users[$username])) {
            return null;
        }

        return $this->users[$username];
    }

    /**
     * Returning user info for broker. Should return json or something like that.
     *
     * @param array|object $user Can be user object or array.
     *
     * @return mixed
     */
    protected function returnUserInfo($user)
    {
        return json_encode($user);
    }

    /**
     * Return session id sent from broker.
     *
     * @return null|string
     */

    protected function getBrokerSessionId()
    {
        $headers = getallheaders();
        if (isset($headers['Authorization']) &&  strpos($headers['Authorization'], 'Bearer') === 0) {
            $headers['Authorization'] = substr($headers['Authorization'], 7);

            return $headers['Authorization'];
        }

        return null;
    }

    /**
     * Start new session when user visits server.
     *
     * @return void
     */
    protected function startUserSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Set session data
     *
     * @param string $key
     * @param null|string $value
     *
     * @return void
     */
    protected function setSessionData(string $key, ?string $value = null)
    {
        if (!$value) {
            unset($_SESSION['key']);
            return;
        }

        $_SESSION[$key] = $value;
    }

    /**
     * Get data saved in session.
     *
     * @param string $key
     *
     * @return null|string
     */
    protected function getSessionData(string $key)
    {
        if ($key === 'id') {
            return session_id();
        }

        if (!isset($_SESSION[$key])) {
            return null;
        }

        return $_SESSION[$key];
    }

    /**
     * Start new session with specific session id.
     *
     * @param $sessionId
     *
     * @return void
     */
    protected function startSession(string $sessionId)
    {
        session_id($sessionId);
        session_start();
    }

    /**
     * Save broker session data to cache.
     *
     * @param string $brokerSessionId
     * @param string $sessionData
     *
     * @return void
     */
    protected function saveBrokerSessionData(string $brokerSessionId, string $sessionData)
    {
        /** This is basic example and you should do something better. */

        $cacheFile = fopen('broker_session_' . $brokerSessionId, 'w');
        fwrite($cacheFile, $sessionData);
        fclose($cacheFile);
    }

    /**
     * Get broker session data from cache.
     *
     * @param string $brokerSessionId
     *
     * @return null|string
     */
    protected function getBrokerSessionData(string $brokerSessionId)
    {
        /** This is basic example and you should do something better. */

        $cacheFileName = 'broker_session_' . $brokerSessionId;

        if (!file_exists($cacheFileName)) {
            return null;
        }

        if (time() - 3600 > filemtime($cacheFileName)) {
            unlink($cacheFileName);

            return null;
        }

        echo file_get_contents($cacheFileName);
    }
}
