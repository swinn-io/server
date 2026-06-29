<?php

namespace App\MessageTypes;

use App\Interfaces\MessageTypeInterface;

class FileReferenceType implements MessageTypeInterface
{
    public function name(): string
    {
        return 'file_reference';
    }

    public function version(): string
    {
        return '1.0';
    }

    public function purpose(): string
    {
        return 'A pointer to an external file: URL, MIME type, and byte size. Swinn carries the reference only, never the file content.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['url', 'mime_type', 'size_bytes'],
            'properties' => [
                'url' => ['type' => 'string', 'format' => 'uri'],
                'mime_type' => ['type' => 'string', 'pattern' => '^[a-z]+/[a-z0-9.+-]+$'],
                'size_bytes' => ['type' => 'integer', 'minimum' => 1],
            ],
        ];
    }

    public function rendererHint(): string
    {
        return 'FileCard';
    }
}
