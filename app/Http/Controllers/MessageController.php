<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageNewRequest;
use App\Http\Requests\MessageStoreRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ThreadResource;
use App\Interfaces\MessageServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;

class MessageController extends Controller
{
    private MessageServiceInterface $service;

    /**
     * MessageController constructor.
     */
    public function __construct(MessageServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Returns pagination of all threads.
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $threads = $this->service->threads($user);

        return ThreadResource::collection($threads);
    }

    /**
     * @return ThreadResource
     */
    public function store(MessageStoreRequest $request)
    {
        $values = $request->validated();
        $user = $request->user();
        $thread = $this->service->newThread(
            $values['subject'],
            $user,
            $values['content'],
            Arr::get($values, 'recipients', [])
        );

        return new ThreadResource($thread);
    }

    /**
     * Returns pagination of all threads.
     *
     * @return ThreadResource
     */
    public function show(string $id)
    {
        $thread = $this->service->thread($id);

        return new ThreadResource($thread);
    }

    /**
     * @return MessageResource
     */
    public function new(string $id, MessageNewRequest $request)
    {
        $values = $request->validated();
        $user = $request->user();
        $thread = $this->service->thread($id);
        $message = $this->service->newMessage(
            $thread,
            $user,
            $values['body'],
        );

        return new MessageResource($message);
    }
}
