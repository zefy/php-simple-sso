<?php

namespace Zefy\SimpleSSO\Interfaces;

interface SSOServerInterface
{
    /**
     * Attach user's session to broker's session.
     *
     * @param string|null $broker Broker's name/id.
     * @param string|null $token Token sent from broker.
     * @param string|null $checksum Calculated broker+token checksum.
     *
     * @return mixed
     */
    public function attach(?string $broker, ?string $token, ?string $checksum);

    /**
     * Login user with provided data.
     *
     * @param string $username User's username.
     * @param string $password User's password.
     *
     * @return mixed
     */
    public function login(?string $username, ?string $password);

    /**
     * Logging out user.
     *
     * @return string Json response.
     */
    public function logout();

    /**
     * Return user info based on client session id associated with broker session id.
     *
     * @return string Json response.
     */
    public function userInfo();
}
