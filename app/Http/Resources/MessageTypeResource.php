<?php

namespace App\Http\Resources;

use App\Interfaces\CrossFieldValidatableInterface;
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

        $data = [
            'type' => $type->name(),
            'version' => $type->version(),
            'purpose' => $type->purpose(),
            'schema' => $type->schema(),
            'renderer_hint' => $type->rendererHint(),
        ];

        if ($type instanceof CrossFieldValidatableInterface) {
            $data['constraints'] = $type->constraints();
        }

        return $data;
    }
}