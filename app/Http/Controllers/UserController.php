<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Interfaces\UserServiceInterface;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private UserServiceInterface $service;

    /**
     * UserController constructor.
     */
    public function __construct(UserServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Returns authenticated API user.
     *
     * @return UserResource
     */
    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return $this->show($user->id);
    }

    /**
     * Returns user by id.
     *
     * @return UserResource
     */
    public function show(string $id)
    {
        return new UserResource(
            $this->service->find($id)
        );
    }
}
