<?php

declare(strict_types=1);

namespace PCP\AST;

final class ForEachNode extends Node
{
    /**
     * @param list<Node> $body
     */
    public function __construct(
        public string $expression,
        public array $body = [],
    ) {
    }
}