<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactStoreRequest;
use App\Http\Resources\ContactResource;
use App\Interfaces\ContactServiceInterface;
use App\Interfaces\UserServiceInterface;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * @var ContactServiceInterface
     */
    private ContactServiceInterface $service;

    /**
     * @var UserServiceInterface
     */
    private UserServiceInterface $userService;

    /**
     * ContactController constructor.
     *
     * @param ContactServiceInterface $service
     * @param UserServiceInterface $userService
     */
    public function __construct(ContactServiceInterface $service, UserServiceInterface $userService)
    {
        $this->service = $service;
        $this->userService = $userService;
    }

    /**
     * Returns user by id.
     *
     * @param string $id
     * @return ContactResource
     */
    public function show(string $id)
    {
        $user = Auth::user();

        return new ContactResource(
            $this->service->contact($id, $user)
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

    /**
     * Store a contact.
     *
     * @param string $user_id
     * @return ContactResource
     */
    public function store(string $user_id): ContactResource
    {
        $user = Auth::user();
        $contact = $this->userService->find($user_id);

        return new ContactResource(
            $this->service->addContact($user, $contact)
        );
    }
}
