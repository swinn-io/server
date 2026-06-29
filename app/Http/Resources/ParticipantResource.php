<?php

namespace App\Http\Resources;

use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Participant
 */
class ParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'type' => 'participant',
            'id' => (string) $this->id,
            'attributes' => [
                'thread_id' => $this->thread_id,
                'thread' => new ThreadResource($this->whenLoaded('tread')),
                'user_id' => $this->user_id,
                'user' => new UserResource($this->whenLoaded('user')),
                'last_read' => $this->last_read,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
