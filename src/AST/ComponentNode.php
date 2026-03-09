<?php

declare(strict_types=1);

namespace PCP\AST;

final class ComponentNode extends Node
{
    /**
     * @param AttributeNode[] $props
     * @param Node[] $children
     */
    public function __construct(
        public string $component,
        public array $props = [],
        public array $children = []
    ) {}
}