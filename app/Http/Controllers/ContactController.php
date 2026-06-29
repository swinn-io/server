<?php

namespace App\Http\Controllers;

use App\Http\Resources\ContactResource;
use App\Interfaces\ContactServiceInterface;
use App\Interfaces\UserServiceInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    private ContactServiceInterface $service;

    private UserServiceInterface $userService;

    /**
     * ContactController constructor.
     */
    public function __construct(ContactServiceInterface $service, UserServiceInterface $userService)
    {
        $this->service = $service;
        $this->userService = $userService;
    }

    /**
     * Returns user by id.
     *
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
     * @return AnonymousResourceCollection
     */
    public function index()
    {
        $user = Auth::user();

        return ContactResource::collection($this->service->contacts($user));
    }

    /**
     * Store a contact.
     */
    public function store(string $user_id): ContactResource
    {
        $user = Auth::user();
        $contact = $this->userService->find($user_id);

        return new ContactResource(
            $this->service->addContact($user, $contact)
        );
    }

    /**
     * Redirects to URI.
     *
     * @return Application|RedirectResponse|Redirector
     */
    public function redirect(Request $request, string $id)
    {
        $user = Auth::user();
        $contact = $this->service->contact($id, $user);

        if ($contact === null) {
            abort(404);
        }

        $URI = $request->get('redirect_uri', config('app.uri'));

        return redirect($URI ?? '/');
    }
}
