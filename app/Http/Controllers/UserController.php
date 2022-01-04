<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Interfaces\UserServiceInterface;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * @var UserServiceInterface
     */
    private UserServiceInterface $service;

    /**
     * UserController constructor.
     *
     * @param  UserServiceInterface  $service
     */
    public function __construct(UserServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Returns authenticated API user.
     *
     * @param  Request  $request
     * @return UserResource
     */
    public function me(Request $request)
    {
        return $this->show($request->user()->id);
    }

    /**
     * Returns user by id.
     *
     * @param  string  $id
     * @return UserResource
     */
    public function show(string $id)
    {
        return new UserResource(
            $this->service->find($id)
        );
    }
}
