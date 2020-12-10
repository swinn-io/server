<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
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
            'type'       => 'contact',
            'id'         => (string) $this->id,
            'attributes' => [
                'name'         => $this->name,
                'user_id'      => $this->user_id,
                'user'         => new UserResource($this->whenLoaded('user')),
                'source'       => new UserResource($this->whenLoaded('source')),
                'created_at'   => $this->created_at,
                'updated_at'   => $this->updated_at,
            ],
        ];
    }
}
