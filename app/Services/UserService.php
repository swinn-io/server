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
        return User::findOrFail($id);
    }

    /**
     * Set user status online.
     *
     * @param string $id
     * @return User
     */
    public function online(string $id): User
    {
        $user = $this->find($id);
        $user->is_online = true;
        $user->save();

        return $user;
    }

    /**
     * Set user status online.
     *
     * @param string $id
     * @return User
     */
    public function offline(string $id): User
    {
        $user = $this->find($id);
        $user->is_online = false;
        $user->save();

        return $user;
    }
}
