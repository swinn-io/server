<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactResource;
use App\Interfaces\ContactServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * @var ContactServiceInterface
     */
    private ContactServiceInterface $service;

    /**
     * ContactController constructor.
     *
     * @param ContactServiceInterface $service
     */
    public function __construct(ContactServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Returns user by id.
     *
     * @param string $id
     * @return ContactResource
     */
    public function show(string $id)
    {
        return new ContactResource(
            $this->service->contact($id)
        );
    }

    /**
     * Returns contacts by user.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $user = Auth::user();

        return ContactResource::collection($this->service->contacts($user));
    }
}
