<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Integration;

use PCP\Env;
use PCP\PCP;
use PCP\PCPConfig;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class CompilerIntegrationTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcp_it_' . bin2hex(random_bytes(6));

        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_compiler_compiles_and_executes_pcp_component(): void
    {
        $componentsDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'components';
        $cacheDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'cache';

        $appComponentsDir = $componentsDir
            . DIRECTORY_SEPARATOR . 'App'
            . DIRECTORY_SEPARATOR . 'Components';

        mkdir($appComponentsDir, 0777, true);
        mkdir($cacheDir);

        $pcpFile = $appComponentsDir . DIRECTORY_SEPARATOR . 'HelloComponent.pcp';

        file_put_contents($pcpFile, <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class HelloComponent extends Component
        {
            public function render(): Node
            {
                $name = "Rodrigo";
        
                return (
                    <h1>Hola { $name }</h1>
                );
            }
        }
        PCP);

        $config = PCPConfig::defaults();
        $config->roots = [$componentsDir];
        $config->cacheDir = $cacheDir;
        $config->env = Env::Dev;

        $pcp = new PCP($config);
        $pcp->registerAutoload();

        $component = new \App\Components\HelloComponent();

        $html = $component->render()->toHtml();

        self::assertSame('<h1>Hola Rodrigo</h1>', trim($html));
    }

    public function test_compiler_handles_nested_markup(): void
    {
        $componentsDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'components';
        $cacheDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'cache';

        $appComponentsDir = $componentsDir
            . DIRECTORY_SEPARATOR . 'App'
            . DIRECTORY_SEPARATOR . 'Components';

        mkdir($appComponentsDir, 0777, true);
        mkdir($cacheDir);

        $pcpFile = $appComponentsDir . DIRECTORY_SEPARATOR . 'NestedComponent.pcp';

        file_put_contents($pcpFile, <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class NestedComponent extends Component
        {
            public function render(): Node
            {
                return (
                    <div><h1>Hola</h1><p>Mundo</p></div>
                );
            }
        }
        PCP);

        $config = PCPConfig::defaults();
        $config->roots = [$componentsDir];
        $config->cacheDir = $cacheDir;
        $config->env = Env::Dev;

        $pcp = new PCP($config);
        $pcp->registerAutoload();

        $component = new \App\Components\NestedComponent();

        $html = $component->render()->toHtml();

        self::assertSame(
            '<div><h1>Hola</h1><p>Mundo</p></div>',
            trim($html),
        );
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