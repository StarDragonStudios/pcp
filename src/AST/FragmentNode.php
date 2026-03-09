<?php

declare(strict_types=1);

namespace PCP\AST;

final class FragmentNode extends Node
{
    /**
     * @param Node[] $children
     */
    public function __construct(
        public array $children = []
    ) {}
}