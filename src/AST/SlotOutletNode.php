<?php

declare(strict_types=1);

namespace PCP\AST;

final class SlotOutletNode extends Node
{
    /**
     * @param string $slotName
     * @param list<Node> $fallbackChildren
     */
    public function __construct(
        public string $slotName,
        public array $fallbackChildren = []
    ) {}
}