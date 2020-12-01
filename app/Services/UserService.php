<?php

namespace App\Services;

use App\Interfaces\UserServiceInterface;
use App\Models\User;

class UserService implements UserServiceInterface
{
    /**
     * Find user.
     *
     * @param string $id
     * @return User
     */
    public function find(string $id): User
    {
        return User::find($id);
    }
}
