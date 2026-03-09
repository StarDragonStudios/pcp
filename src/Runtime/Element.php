<?php

declare(strict_types=1);

namespace PCP\Runtime;

final class Element extends Node
{
    /**
     * @param array<string, mixed> $attributes
     * @param list<Node|string|int|float|bool|null> $children
     */
    public function __construct(
        private readonly string $tag,
        private readonly array $attributes = [],
        private readonly array $children = [],
    ) {
    }

    public function toHtml(): string
    {
        $attributes = $this->renderAttributes();
        $html = '<' . $this->tag . $attributes . '>';

        foreach ($this->children as $child) {
            $html .= Runtime::normalizeChild($child)->toHtml();
        }

        $html .= '</' . $this->tag . '>';

        return $html;
    }

    private function renderAttributes(): string
    {
        if ($this->attributes === []) {
            return '';
        }

        $parts = [];

        foreach ($this->attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            if ($value === true) {
                $parts[] = $name;
                continue;
            }

            $parts[] = sprintf(
                '%s="%s"',
                $name,
                $this->escape((string) $value),
            );
        }

        return $parts === [] ? '' : ' ' . implode(' ', $parts);
    }
}