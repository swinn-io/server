<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'type'       => 'message',
            'id'         => (string) $this->id,
            'attributes' => [
                'thread_id'    => $this->thread_id,
                'thread'       => new ThreadResource($this->whenLoaded('tread')),
                'user_id'      => $this->user_id,
                'user'         => new UserResource($this->whenLoaded('user')),
                'body'         => $this->body,
                'created_at'   => $this->created_at,
                'updated_at'   => $this->updated_at,
            ],
        ];
    }
}
