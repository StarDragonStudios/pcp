<?php

declare(strict_types=1);

namespace PCP\AST;

final class ElementNode extends Node
{
    /**
     * @param AttributeNode[] $attributes
     * @param Node[] $children
     */
    public function __construct(
        public string $tag,
        public array $attributes = [],
        public array $children = []
    ) {}
}