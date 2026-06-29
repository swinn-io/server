<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;

class InvalidEnvelopeException extends HttpResponseException
{
    /** @param array<string, mixed> $descriptor */
    public function __construct(array $descriptor)
    {
        parent::__construct(response()->json($descriptor, 422));
    }
}
