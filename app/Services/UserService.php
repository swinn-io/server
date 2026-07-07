<?php

namespace App\Services;

use App\Interfaces\UserServiceInterface;
use App\Models\User;

class UserService implements UserServiceInterface
{
    /**
     * Find user.
     */
    public function find(string $id): User
    {
        return User::findOrFail($id);
    }
}
