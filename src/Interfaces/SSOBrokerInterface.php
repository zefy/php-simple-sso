<?php

namespace Zefy\SimpleSSO\Interfaces;

interface SSOBrokerInterface
{
    /**
     * Attach client session to broker session in SSO server.
     *
     * @return void
     */
    public function attach();

    /**
     * Getting user info from SSO based on client session.
     *
     * @return array
     */
    public function getUserInfo();

    /**
     * Login client to SSO server with user credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function login(string $username, string $password);

    /**
     * Logout client from SSO server.
     *
     * @return void
     */
    public function logout();
}
