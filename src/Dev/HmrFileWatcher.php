<?php

declare(strict_types=1);

namespace PCP\Dev;

use PCP\PCPConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class HmrFileWatcher
{
    public function __construct(
        private PCPConfig $config,
    ) {
    }

    public function fingerprint(): string
    {
        $entries = [];

        foreach ($this->config->roots as $root) {
            if (!is_dir($root)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $path = $file->getPathname();

                if (!$this->isWatchedComponentFile($path)) {
                    continue;
                }

                $mtime = $file->getMTime();
                $entries[] = $path . '|' . $mtime;
            }
        }

        sort($entries);

        return sha1(implode("\n", $entries));
    }

    public function waitForChange(?string $since = null, int $timeoutSeconds = 25): ?string
    {
        $initial = $since ?? $this->fingerprint();
        $deadline = time() + $timeoutSeconds;

        while (time() < $deadline) {
            clearstatcache();

            $current = $this->fingerprint();

            if ($current !== $initial) {
                return $current;
            }

            usleep($this->config->hmrPollIntervalMs * 1000);
        }

        return null;
    }

    private function isWatchedComponentFile(string $path): bool
    {
        $path = strtolower($path);

        return str_ends_with($path, '.pcp') || str_ends_with($path, '.php');
    }
}