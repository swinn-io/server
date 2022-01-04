<?php

namespace App\Interfaces;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * Find user.
     *
     * @param  string  $id
     * @return User
     */
    public function find(string $id): User;
}
