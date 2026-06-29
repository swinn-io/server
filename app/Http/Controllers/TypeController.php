<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageTypeResource;
use App\Services\TypeRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TypeController extends Controller
{
    /**
     * Public discovery of the message type registry.
     *
     * @return AnonymousResourceCollection
     */
    public function index(TypeRegistry $registry)
    {
        MessageTypeResource::withoutWrapping();

        return MessageTypeResource::collection(array_values($registry->all()));
    }
}
