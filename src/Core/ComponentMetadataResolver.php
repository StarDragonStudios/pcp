<?php

declare(strict_types=1);

namespace PCP\Core;

use PCP\ClientSide;
use PCP\ComponentMetadata;
use PCP\Island;
use PCP\ServerSide;
use ReflectionClass;
use RuntimeException;

final class ComponentMetadataResolver
{
    /**
     * @var array<class-string, ComponentMetadata>
     */
    private array $cache = [];

    public function resolve(string $class): ComponentMetadata
    {
        if (isset($this->cache[$class])) {
            return $this->cache[$class];
        }

        if (!class_exists($class)) {
            throw new RuntimeException(sprintf(
                'Cannot resolve PCP component metadata for "%s": class does not exist.',
                $class,
            ));
        }

        $reflection = new ReflectionClass($class);

        $serverSide = $reflection->getAttributes(ServerSide::class) !== [];
        $clientSide = $reflection->getAttributes(ClientSide::class) !== [];
        $islandAttributes = $reflection->getAttributes(Island::class);

        $island = $islandAttributes !== [];
        $strategy = null;

        if ($island) {
            /** @var Island $instance */
            $instance = $islandAttributes[0]->newInstance();
            $strategy = $this->normalizeStrategy($instance->strategy);
        }

        // Default razonable: si no se marca nada, asumimos SSR normal.
        if (!$serverSide && !$clientSide && !$island) {
            $serverSide = true;
        }

        if ($clientSide && $island) {
            throw new RuntimeException(sprintf(
                'PCP component "%s" cannot be both #[ClientSide] and #[Island].',
                $class,
            ));
        }

        if ($serverSide && $clientSide) {
            throw new RuntimeException(sprintf(
                'PCP component "%s" cannot be both #[ServerSide] and #[ClientSide].',
                $class,
            ));
        }

        if ($serverSide && $island) {
            // SSR + hydration: permitido, y además es justo lo que es una isla.
            // Dejamos serverSide=true e island=true.
        }

        return $this->cache[$class] = new ComponentMetadata(
            serverSide: $serverSide,
            clientSide: $clientSide,
            island: $island,
            islandStrategy: $strategy,
        );
    }

    private function normalizeStrategy(string $strategy): string
    {
        $strategy = strtolower(trim($strategy));

        return match ($strategy) {
            'load', 'visible', 'idle', 'interaction' => $strategy,
            default => throw new RuntimeException(sprintf(
                'Unsupported PCP island strategy "%s".',
                $strategy,
            )),
        };
    }
}