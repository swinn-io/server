<?php

namespace App\Services;

use App\Interfaces\CrossFieldValidatableInterface;
use App\Interfaces\MessageTypeInterface;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Validator;

class TypeRegistry
{
    /** @var array<string, MessageTypeInterface> */
    private array $types = [];

    /** @param iterable<MessageTypeInterface> $types */
    public function __construct(iterable $types = [])
    {
        foreach ($types as $type) {
            $this->types[$type->name()] = $type;
        }
    }

    /** @return array<string, MessageTypeInterface> */
    public function all(): array
    {
        return $this->types;
    }

    public function has(string $name): bool
    {
        return isset($this->types[$name]);
    }

    public function get(string $name): ?MessageTypeInterface
    {
        return $this->types[$name] ?? null;
    }

    /**
     * Validate a full envelope. Returns null on success, or an error descriptor.
     *
     * @return array<string, mixed>|null
     */
    public function validate(mixed $envelope): ?array
    {
        if (! is_array($envelope)
            || array_keys($envelope) === array_keys(array_keys($envelope))
        ) {
            return ['error' => 'invalid_envelope', 'message' => 'Envelope must be an object.'];
        }

        $keys = array_keys($envelope);
        sort($keys);
        if ($keys !== ['payload', 'type', 'version']) {
            return ['error' => 'invalid_envelope', 'message' => 'Envelope must contain exactly type, version, and payload.'];
        }

        if (! is_string($envelope['type']) || ! is_string($envelope['version'])) {
            return ['error' => 'invalid_envelope', 'message' => 'type and version must be strings.'];
        }

        if (! is_array($envelope['payload'])) {
            return ['error' => 'invalid_envelope', 'message' => 'payload must be an object.'];
        }

        /** @var array<string, mixed> $payload */
        $payload = $envelope['payload'];

        $type = $this->get($envelope['type']);

        if ($type === null) {
            return ['error' => 'unknown_type', 'type' => $envelope['type']];
        }

        if ($envelope['version'] !== $type->version()) {
            return ['error' => 'unknown_version', 'type' => $type->name(), 'version' => $envelope['version']];
        }

        /** @var bool|object|string $schema */
        $schema = Helper::toJSON($type->schema());

        $result = (new Validator)->validate(
            Helper::toJSON($payload),
            $schema,
        );

        $error = $result->error();

        if ($error !== null) {
            return [
                'error' => 'invalid_payload',
                'violations' => (new ErrorFormatter)->format($error),
            ];
        }

        if ($type instanceof CrossFieldValidatableInterface) {
            $violations = $type->validate($payload);

            if (! empty($violations)) {
                return ['error' => 'invalid_payload', 'violations' => $violations];
            }
        }

        return null;
    }
}
