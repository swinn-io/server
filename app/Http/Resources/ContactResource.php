<?php

namespace App\Http\Resources;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contact
 */
class ContactResource extends JsonResource
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
            'type' => 'contact',
            'id' => (string) $this->id,
            'attributes' => [
                'name' => $this->name,
                'user_id' => $this->user_id,
                'user' => new UserResource($this->whenLoaded('user')),
                'source_type' => $this->source_type,
                'source_id' => $this->source_id,
                'source' => new UserResource($this->whenLoaded('source')),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
