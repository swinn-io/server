<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Interfaces\MessageServiceInterface;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    /**
     * @var MessageServiceInterface
     */
    private MessageServiceInterface $service;

    /**
     * MessageController constructor.
     *
     * @param MessageServiceInterface $service
     */
    public function __construct(MessageServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Return users which participated in the same threads of authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $participants = $this->service->allParticipants($user->id);

        return UserResource::collection($participants);
    }
}
