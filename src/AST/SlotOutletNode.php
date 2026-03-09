<?php

declare(strict_types=1);

namespace PCP\AST;

final class SlotOutletNode extends Node
{
    public function __construct(
        public string $slotName,
    ) {
    }
}