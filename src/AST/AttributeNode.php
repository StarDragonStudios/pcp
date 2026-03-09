<?php

declare(strict_types=1);

namespace PCP\AST;

final class AttributeNode extends Node
{
    public function __construct(
        public string $name,
        public string|bool|ExpressionNode $value
    ) {}
}