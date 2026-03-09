<?php

declare(strict_types = 1);

namespace PCP;

use PCP\PCP;

final class PCPBuilder
{
    private PCPConfig $config;

    public function __construct(?PCPConfig $config = null)
    {
        $this->config = $config ?? PCPConfig::defaults();
    }

    public function roots(array $roots): self
    {
        $this->config->roots = $roots;
        return $this;
    }

    public function addRoot(string $root): self
    {
        $roots = $this->config->roots;
        $roots[] = $root;
        $this->config->roots = $roots;

        return $this;
    }

    public function cacheDir(string $cacheDir): self
    {
        $this->config->cacheDir = $cacheDir;
        return $this;
    }

    public function env(Env $env): self
    {
        $this->config->env = $env;
        return $this;
    }

    public function hmr(bool $enabled): self
    {
        $this->config->hmr = $enabled;
        return $this;
    }

    public function hmrIntervalMs(int $intervalMs): self
    {
        $this->config->hmrIntervalMs = $intervalMs;
        return $this;
    }

    public function componentsNamespace(string $namespace): self
    {
        $this->config->componentsNamespace = $namespace;
        return $this;
    }

    public function build(): PCP
    {
        return new PCP(clone $this->config);
    }

    public function config(): PCPConfig
    {
        return clone $this->config;
    }
}