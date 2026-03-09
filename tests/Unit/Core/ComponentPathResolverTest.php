<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Core;

use PCP\Core\ComponentPathResolver;
use PCP\PCPConfig;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use RuntimeException;

final class ComponentPathResolverTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcp_test_' . bin2hex(random_bytes(8));

        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_resolve_finds_pcp_file_first(): void
    {
        $root = $this->tmpDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Components';
        mkdir($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components', 0777, true);

        file_put_contents($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.pcp', 'namespace App\Components;');
        file_put_contents($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.php', '<?pcp namespace App\Components;');

        $config = PCPConfig::defaults();
        $config->roots = [$root];

        $resolver = new ComponentPathResolver($config);

        $resolved = $resolver->resolve('App\\Components\\HomeComponent');

        self::assertSame(
            $this->normalizePath($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.pcp'),
            $this->normalizePath($resolved),
        );
    }

    public function test_resolve_finds_php_file_when_pcp_does_not_exist(): void
    {
        $root = $this->tmpDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Components';
        mkdir($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components', 0777, true);

        file_put_contents($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.php', '<?pcp namespace App\Components;');

        $config = PCPConfig::defaults();
        $config->roots = [$root];

        $resolver = new ComponentPathResolver($config);

        $resolved = $resolver->resolve('App\\Components\\HomeComponent');

        self::assertSame(
            $this->normalizePath($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.php'),
            $this->normalizePath($resolved),
        );
    }

    public function test_resolve_returns_null_when_component_does_not_exist(): void
    {
        $root = $this->tmpDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Components';
        mkdir($root, 0777, true);

        $config = PCPConfig::defaults();
        $config->roots = [$root];

        $resolver = new ComponentPathResolver($config);

        self::assertNull(
            $resolver->resolve('App\\Components\\MissingComponent')
        );
    }

    public function test_require_component_returns_path_when_found(): void
    {
        $root = $this->tmpDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Components';
        mkdir($root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components', 0777, true);

        $path = $root . DIRECTORY_SEPARATOR . 'App' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'HomeComponent.pcp';
        file_put_contents($path, 'namespace App\Components;');

        $config = PCPConfig::defaults();
        $config->roots = [$root];

        $resolver = new ComponentPathResolver($config);

        self::assertSame(
            $this->normalizePath($path),
            $this->normalizePath($resolver->requireComponent('App\\Components\\HomeComponent'))
        );
    }

    public function test_require_component_throws_when_not_found(): void
    {
        $root = $this->tmpDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Components';
        mkdir($root, 0777, true);

        $config = PCPConfig::defaults();
        $config->roots = [$root];

        $resolver = new ComponentPathResolver($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PCP component "App\\Components\\MissingComponent" could not be resolved.');

        $resolver->requireComponent('App\\Components\\MissingComponent');
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}