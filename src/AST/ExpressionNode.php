<?php

declare(strict_types=1);

namespace PCP\AST;

final class ExpressionNode extends Node
{
    public function __construct(
        public string $expression,
    ) {}
}