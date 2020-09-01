<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageNewRequest;
use App\Http\Requests\MessageStoreRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\TreadResource;
use App\Interfaces\MessageServiceInterface;
use Illuminate\Http\Request;

class MessageController extends Controller
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
     * Returns pagination of all threads.
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $threads = $this->service->threads($user->id);

        return TreadResource::collection($threads);
    }

    /**
     * @param MessageStoreRequest $request
     * @return TreadResource
     */
    public function store(MessageStoreRequest $request)
    {
        $values = $request->validated();
        $user = $request->user();
        $threads = $this->service->newThread(
            $values['subject'],
            $user->id,
            $values['content'],
            $values['recipients'] ?? null
        );

        return new TreadResource($threads);
    }

    /**
     * @param string $id
     * @param MessageNewRequest $request
     * @return MessageResource
     */
    public function new(string $id, MessageNewRequest $request)
    {
        $values = $request->validated();
        $user = $request->user();
        $threads = $this->service->newMessage(
            $id,
            $user->id,
            $values['content']
        );

        return new MessageResource($threads);
    }
}
