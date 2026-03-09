<?php

declare(strict_types=1);

namespace PCP;

use PCP\Compiling\Compiler;
use PCP\Core\ComponentPathResolver;

final readonly class PCP
{
    public function __construct(
        public PCPConfig $config = new PCPConfig(),
    ) {
    }

    public static function defaults(): self
    {
        return new self(PCPConfig::defaults());
    }

    public static function prodDefaults(): self
    {
        return new self(PCPConfig::prodDefaults());
    }

    public static function testDefaults(): self
    {
        return new self(PCPConfig::testDefaults());
    }

    public static function fromConfig(PCPConfig $config): self
    {
        return new self(clone $config);
    }

    public static function builder(): PCPBuilder
    {
        return new PCPBuilder();
    }

    public function withConfig(PCPConfig $config): self
    {
        return new self(clone $config);
    }

    public function isDev(): bool
    {
        return $this->config->env->isDev();
    }

    public function isProd(): bool
    {
        return $this->config->env->isProd();
    }

    public function isTest(): bool
    {
        return $this->config->env->isTest();
    }

    public function registerAutoload(): void
    {
        spl_autoload_register(function (string $class): void {
            $resolver = new ComponentPathResolver($this->config);
            $path = $resolver->resolve($class);

            if ($path === null) {
                return;
            }

            $compiler = new Compiler($this->config);
            $compiled = $compiler->compileIfNeeded($class, $path);

            require_once $compiled;
        });
    }

    public function hmrClientScript(): string
    {
        if (!$this->isDev() || !$this->config->hmr) {
            return '';
        }

        $endpoint = htmlspecialchars($this->config->hmrEndpoint, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
        <script>
        (() => {
            let lastFingerprint = null;
            let source = null;
        
            const connect = () => {
                const url = new URL({$this->quoteJs($endpoint)}, window.location.origin);
        
                if (lastFingerprint) {
                    url.searchParams.set('since', lastFingerprint);
                }
        
                source = new EventSource(url.toString());
        
                source.addEventListener('ready', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.fingerprint) {
                            lastFingerprint = data.fingerprint;
                        }
                    } catch (_) {}
                });
        
                source.addEventListener('ping', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.fingerprint) {
                            lastFingerprint = data.fingerprint;
                        }
                    } catch (_) {}
        
                    source.close();
                    setTimeout(connect, 250);
                });
        
                source.addEventListener('reload', (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.fingerprint) {
                            lastFingerprint = data.fingerprint;
                        }
                    } catch (_) {}
        
                    window.location.reload();
                });
        
                source.addEventListener('error', () => {
                    try {
                        source.close();
                    } catch (_) {}
        
                    setTimeout(connect, 1000);
                });
            };
        
            connect();
        })();
        </script>
        HTML;
    }

    private function quoteJs(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '""';
    }
}