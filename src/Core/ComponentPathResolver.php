<?php

declare(strict_types=1);

namespace PCP\Core;

use PCP\PCPConfig;
use RuntimeException;

final readonly class ComponentPathResolver
{
    public function __construct(
        private PCPConfig $config,
    ) {
    }

    public function resolve(string $class): ?string
    {
        $relativePath = $this->classToRelativePath($class);

        foreach ($this->config->roots as $root) {
            $pcpPath = $root . DIRECTORY_SEPARATOR . $relativePath . '.pcp';
            if (is_file($pcpPath)) {
                return $pcpPath;
            }

            $phpPath = $root . DIRECTORY_SEPARATOR . $relativePath . '.php';
            if (is_file($phpPath)) {
                return $phpPath;
            }
        }

        return null;
    }

    private function classToRelativePath(string $class): string
    {
        return str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\'));
    }

    public function requireComponent(string $class): string
    {
        $path = $this->resolve($class);

        if ($path === null) {
            throw new RuntimeException(sprintf(
                'PCP component "%s" could not be resolved.',
                $class,
            ));
        }

        return $path;
    }
}