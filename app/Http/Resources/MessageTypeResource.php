<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageTypeResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var \App\Interfaces\MessageTypeInterface $type */
        $type = $this->resource;

        return [
            'type' => $type->name(),
            'version' => $type->version(),
            'purpose' => $type->purpose(),
            'schema' => $type->schema(),
            'renderer_hint' => $type->rendererHint(),
        ];
    }
}