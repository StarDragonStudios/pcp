<?php

declare(strict_types = 1);

namespace PCP;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ServerSide
{
    public function __construct() {}
}