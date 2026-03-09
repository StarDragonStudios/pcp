<?php

declare(strict_types = 1);

namespace PCP\AST;

final class NamedSlotUsageNode extends Node
{
    /**
     * @param list<Node> $children
     */
    public function __construct(
        public string $parentComponent,
        public string $slotName,
        public array $children = [],
    ) {}
}