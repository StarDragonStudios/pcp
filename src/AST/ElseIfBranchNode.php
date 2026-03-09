<?php

declare(strict_types=1);

namespace PCP\AST;

final class ElseIfBranchNode extends Node
{
    /**
     * @param list<Node> $body
     */
    public function __construct(
        public string $condition,
        public array $body = [],
    ) {
    }
}