<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Returns authenticated API user.
     *
     * @param Request $request
     * @return UserResource
     */
    public function me(Request $request)
    {
        return new UserResource(
            $request->user()
        );
    }
}
