<?php

declare(strict_types=1);

namespace PCP\Runtime;

abstract class Node
{
    abstract public function toHtml(): string;

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}