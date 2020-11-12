<?php

namespace App\Interfaces;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * Find user.
     *
     * @param string $id
     * @return User
     */
    public function find(string $id): User;

    /**
     * Set user status online.
     *
     * @param string $id
     * @return User
     */
    public function online(string $id): User;

    /**
     * Set user status online.
     *
     * @param string $id
     * @return User
     */
    public function offline(string $id): User;
}
