<?php

namespace App\Http\Resources;

use App\Interfaces\CrossFieldValidatableInterface;
use App\Interfaces\MessageTypeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTypeResource extends JsonResource
{
    /**
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        /** @var MessageTypeInterface $type */
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
