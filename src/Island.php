<?php

declare(strict_types = 1);

namespace PCP;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Island
{
    public function __construct(
        public string $strategy = 'load',
    ) {
    }
}