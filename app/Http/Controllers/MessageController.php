<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageNewRequest;
use App\Http\Requests\MessageStoreRequest;
use App\Http\Resources\MessageResource;
use App\Http\Resources\ThreadResource;
use App\Interfaces\MessageServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

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

        return ThreadResource::collection($threads);
    }

    /**
     * @param MessageStoreRequest $request
     * @return ThreadResource
     */
    public function store(MessageStoreRequest $request)
    {
        $values = $request->validated();
        $user = $request->user();
        $threads = $this->service->newThread(
            $values['subject'],
            $user,
            $values['content'],
            Arr::get($values, 'recipients', [])
        );

        return new ThreadResource($threads);
    }

    /**
     * Returns pagination of all threads.
     *
     * @param string $id
     * @return ThreadResource
     */
    public function show(string $id)
    {
        $thread = $this->service->thread($id);

        return new ThreadResource($thread);
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
        $thread = $this->service->thread($id);
        $message = $this->service->newMessage(
            $thread,
            $user,
            $values['content'],
        );

        return new MessageResource($message);
    }
}
