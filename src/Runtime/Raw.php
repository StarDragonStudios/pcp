<?php

declare(strict_types=1);

namespace PCP\Runtime;

final class Raw extends Node
{
    public function __construct(
        private readonly string $html,
    ) {
    }

    public function toHtml(): string
    {
        return $this->html;
    }
}