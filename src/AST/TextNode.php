<?php

declare(strict_types=1);

namespace PCP\AST;

final class TextNode extends Node
{
    public function __construct(
        public string $text,
    ) {}
}