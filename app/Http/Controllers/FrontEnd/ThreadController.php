<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Interfaces\MessageServiceInterface;
use App\Models\Message;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ThreadController extends Controller
{
    public MessageServiceInterface $service;

    public function __construct(MessageServiceInterface $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @return void
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return void
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return void
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return View
     */
    public function show(string $thread)
    {
        $thread = $this->service->thread($thread);

        /** @var User $user */
        $user = Auth::user();
        $this->service->markAsRead($thread, $user);

        return view('thread', [
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'messages_count' => $thread->messages->count(),
                'participants' => $thread->participants->map(fn (Participant $participant) => [
                    'user' => ['id' => $participant->user?->id, 'name' => $participant->user?->name],
                ])->values()->all(),
                'messages' => $thread->messages->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at?->diffForHumans(),
                    'user' => ['id' => $message->user?->id, 'name' => $message->user?->name],
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return void
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return void
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return void
     */
    public function destroy($id)
    {
        //
    }
}
