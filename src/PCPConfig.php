<?php

declare(strict_types = 1);

namespace PCP;

use InvalidArgumentException as InvArgExc;

final class PCPConfig
{
    /**
     * @var list<string>
     */
    public array $roots = [] {
        set(array $value) {
            if ($value === []) throw new InvArgExc('PCP roots cannot be empty.');

            $normalized = [];

            foreach ($value as $root) {
                if (!is_string($root)) throw new InvArgExc('Each PCP root must be a string.');
                $root = trim($root);
                if ($root === '') throw new InvArgExc('PCP root paths cannot be empty.');
                $root = rtrim($root, '/\\');
                if ($root === "") throw new InvArgExc('Each PCP root path must be a string.');
                $normalized[] = $root;
            }

            $this->roots = array_values(array_unique($normalized));
        }
    }

    public string $cacheDir = 'var/pcp' {
         set (string $value) {
            $value = trim($value);

            if ($value === '') throw new InvArgExc('PCP cacheDir cannot be empty.');

            $value = rtrim($value, '/\\');

            if ($value === '') throw new InvArgExc('PCP cacheDir cannot be reduced to an empty value.');

            $this->cacheDir = $value;
        }
    }

    public Env $env = Env::Dev;

    public bool $hmr = true;

    public string $hmrEndpoint = '/_pcp/hmr' {
        set (string $value) {
            $value = trim($value);
            if ($value === '') throw new InvArgExc('PCP hmrEndpoint cannot be empty.');
            if ($value[0] !== '/') throw new InvArgExc('PCP hmrEndpoint must start with "/".');

            $this->hmrEndpoint = $value;
        }
    }

    public int $hmrIntervalMs = 500 {
        set(int $value) {
            if ($value < 100) throw new InvArgExc('PCP hmrIntervalMs must be at least 100.');
            $this->hmrIntervalMs = $value;
        }
    }

    public string $componentsNamespace = 'App\\Components' {
        set(string $value) {
            $value = trim($value, '\t\n\r\0\x0B\\');
            if ($value === '') throw new InvArgExc('PCP componentsNamespace cannot be empty.');
            $this->componentsNamespace = $value;
        }
    }

    public static function defaults(): self
    {
        return new self();
    }

    public static function prodDefaults(): self
    {
        $config = new self();
        $config->env = Env::Prod;
        $config->hmr = false;

        return $config;
    }

    public static function testDefaults(): self
    {
        $config = new self();
        $config->env = Env::Test;
        $config->hmr = false;

        return $config;
    }
}