<?php

namespace App\Interfaces;

interface MessageTypeInterface
{
    /** Unique snake_case slug, e.g. "currency". */
    public function name(): string;

    /** Semver string, e.g. "1.0". */
    public function version(): string;

    /** One-paragraph plain-English description for the /types endpoint. */
    public function purpose(): string;

    /**
     * JSON Schema array used for write-time payload validation.
     *
     * @return array<string, mixed>
     */
    public function schema(): array;

    /** Component name hint for human clients, e.g. "CurrencyCard". */
    public function rendererHint(): string;
}
