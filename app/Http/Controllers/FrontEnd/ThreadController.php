<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Interfaces\MessageServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @return Response
     */
    public function show(string $thread)
    {
        $thread = $this->service->thread($thread);

        return view('thread', [
            'thread' => [
                'id' => $thread->id,
                'subject' => $thread->subject,
                'messages' => $thread->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'created_at' => $message->created_at->diffForHumans(),
                    'user' => ['name' => $message->user->name],
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
