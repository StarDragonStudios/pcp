<?php

declare(strict_types=1);

namespace PCP\Parsing;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public mixed     $value,
        public int       $offset,
    ) {
    }
}