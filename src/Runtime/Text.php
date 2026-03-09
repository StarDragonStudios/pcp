<?php

declare(strict_types=1);

namespace PCP\Runtime;

final class Text extends Node
{
    public function __construct(
        private readonly string $text,
        private readonly bool $escape = true,
    ) {
    }

    public function toHtml(): string
    {
        return $this->escape
            ? $this->escape($this->text)
            : $this->text;
    }
}