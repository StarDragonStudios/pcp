<?php

declare(strict_types=1);

namespace PCP\Runtime;

final class Element extends Node
{
    /**
     * @var array<string, true>
     */
    private const array VOID_ELEMENTS = [
        'area' => true,
        'base' => true,
        'br' => true,
        'col' => true,
        'embed' => true,
        'hr' => true,
        'img' => true,
        'input' => true,
        'link' => true,
        'meta' => true,
        'param' => true,
        'source' => true,
        'track' => true,
        'wbr' => true,
    ];

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
        $tag = strtolower($this->tag);
        $attributes = $this->renderAttributes();

        if ($this->isVoidElement($tag)) {
            return '<' . $tag . $attributes . '>';
        }

        $html = '<' . $tag . $attributes . '>';

        foreach ($this->children as $child) {
            $html .= Runtime::normalizeChild($child)->toHtml();
        }

        $html .= '</' . $tag . '>';

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

    private function isVoidElement(string $tag): bool
    {
        return isset(self::VOID_ELEMENTS[$tag]);
    }
}