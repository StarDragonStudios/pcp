<?php

declare(strict_types=1);

namespace PCP\AST;

final class IfNode extends Node
{
    /**
     * @param list<Node> $then
     * @param list<ElseIfBranchNode> $elseIfBranches
     * @param list<Node> $else
     */
    public function __construct(
        public string $condition,
        public array $then = [],
        public array $elseIfBranches = [],
        public array $else = [],
    ) {
    }
}