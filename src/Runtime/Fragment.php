<?php

declare(strict_types=1);

namespace PCP\Runtime;

final class Fragment extends Node
{
    /**
     * @param list<Node|string|int|float|bool|null> $children
     */
    public function __construct(
        private readonly array $children = [],
    ) {
    }

    public function toHtml(): string
    {
        $html = '';

        foreach ($this->children as $child) {
            $html .= Runtime::normalizeChild($child)->toHtml();
        }

        return $html;
    }
}