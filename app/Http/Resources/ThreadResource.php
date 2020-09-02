<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ThreadResource extends JsonResource
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
            'type'       => 'thread',
            'id'         => (string) $this->id,
            'attributes' => [
                'subject'      => $this->subject,
                'messages'     => MessageResource::collection($this->messages),
                'participants' => ParticipantResource::collection($this->participants),
                'created_at'   => $this->created_at,
                'updated_at'   => $this->updated_at,
            ],
        ];
    }
}
