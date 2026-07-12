<?php

namespace App\Interfaces;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * Find user.
     */
    public function find(string $id): User;
}
