<?php

namespace App\Interfaces;

interface CrossFieldValidatableInterface
{
    /**
     * Validate constraints that span multiple fields, run after JSON Schema
     * validation has already passed. Returns a list of violation strings;
     * an empty array means the payload is valid.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function validate(array $payload): array;

    /**
     * Machine-readable constraint vocabulary surfaced on the /types endpoint
     * (e.g. the quantity/unit compatibility matrix).
     *
     * @return array<string, mixed>
     */
    public function constraints(): array;
}
