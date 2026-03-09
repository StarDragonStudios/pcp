<?php

declare(strict_types=1);

namespace PCP;

final readonly class ComponentMetadata
{
    public function __construct(
        public bool $serverSide = false,
        public bool $clientSide = false,
        public bool $island = false,
        public ?string $islandStrategy = null,
    ) {
    }

    public function isInteractive(): bool
    {
        return $this->clientSide || $this->island;
    }
}