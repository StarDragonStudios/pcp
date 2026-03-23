<?php

declare(strict_types=1);

namespace PCP\Tests\Integration;

use PCP\Compiling\Compiler;
use PCP\Core\ComponentPathResolver;
use PCP\Env;
use PCP\PCPConfig;
use PHPUnit\Framework\TestCase;
use Random\RandomException;

final class CompilerSlotsIntegrationTest extends TestCase
{
    private string $tmpDir;

    /**
     * @throws RandomException
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcp_slots_it_' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_compiler_handles_named_slots_end_to_end(): void
    {
        $componentsDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'components';
        $cacheDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'cache';

        $appComponentsDir = $componentsDir
            . DIRECTORY_SEPARATOR . 'App'
            . DIRECTORY_SEPARATOR . 'Components';

        mkdir($appComponentsDir, 0777, true);
        mkdir($cacheDir);

        file_put_contents($appComponentsDir . DIRECTORY_SEPARATOR . 'Card.pcp', <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class Card extends Component
        {
            public function render(): Node
            {
                return (
                    <article>
                        <div class="card__header"><Slot:Header /></div>
                        <div class="card__body"><Slot:Body /></div>
                        <div class="card__footer"><Slot:Footer /></div>
                    </article>
                );
            }
        }
        PCP);

        file_put_contents($appComponentsDir . DIRECTORY_SEPARATOR . 'Page.pcp', <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class Page extends Component
        {
            public function render(): Node
            {
                return (
                    <Card>
                        <Card\Header><header>Cabecera</header></Card\Header>
                        <Card\Body><p>Contenido principal</p></Card\Body>
                        <Card\Footer><footer>Pie</footer></Card\Footer>
                    </Card>
                );
            }
        }
        PCP);

        $config = PCPConfig::defaults();
        $config->roots = [$componentsDir];
        $config->cacheDir = $cacheDir;
        $config->env = Env::Dev;

        $resolver = new ComponentPathResolver($config);
        $compiler = new Compiler($config);

        $cardSource = $resolver->requireComponent('App\\Components\\Card');
        $cardCompiled = $compiler->compileIfNeeded('App\\Components\\Card', $cardSource);
        require $cardCompiled;

        $pageSource = $resolver->requireComponent('App\\Components\\Page');
        $pageCompiled = $compiler->compileIfNeeded('App\\Components\\Page', $pageSource);
        require $pageCompiled;

        self::assertStringContainsString("'header' =>", file_get_contents($pageCompiled));
        self::assertStringContainsString("'body' =>", file_get_contents($pageCompiled));
        self::assertStringContainsString("'footer' =>", file_get_contents($pageCompiled));

        self::assertStringContainsString('$this->header', file_get_contents($cardCompiled));
        self::assertStringContainsString('$this->body', file_get_contents($cardCompiled));
        self::assertStringContainsString('$this->footer', file_get_contents($cardCompiled));

        $page = new \App\Components\Page();

        $html = $page->render()->toHtml();

        self::assertSame(
            '<article><div class="card__header"><header>Cabecera</header></div><div class="card__body"><p>Contenido principal</p></div><div class="card__footer"><footer>Pie</footer></div></article>',
            $this->normalizeHtml($html),
        );
    }

    public function test_compiler_handles_slot_fallback_end_to_end(): void
    {
        $componentsDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'components_fallback';
        $cacheDir = $this->tmpDir . DIRECTORY_SEPARATOR . 'cache_fallback';

        $appComponentsDir = $componentsDir
            . DIRECTORY_SEPARATOR . 'App'
            . DIRECTORY_SEPARATOR . 'Components';

        mkdir($appComponentsDir, 0777, true);
        mkdir($cacheDir);

        file_put_contents($appComponentsDir . DIRECTORY_SEPARATOR . 'CardFallback.pcp', <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class CardFallback extends Component
        {
            public function render(): Node
            {
                return (
                    <article>
                        <div class="card__header">
                            <Slot:Header><h1>Header por defecto</h1></Slot:Header>
                        </div>
                        <div class="card__body">
                            <Slot:Body />
                        </div>
                    </article>
                );
            }
        }
        PCP);

        file_put_contents($appComponentsDir . DIRECTORY_SEPARATOR . 'PageFallback.pcp', <<<'PCP'
        namespace App\Components;
        
        use PCP\Component;
        use PCP\Runtime\Node;
        
        final class PageFallback extends Component
        {
            public function render(): Node
            {
                return (
                    <CardFallback>
                        <CardFallback\Body><p>Contenido principal</p></CardFallback\Body>
                    </CardFallback>
                );
            }
        }
        PCP);

        $config = PCPConfig::defaults();
        $config->roots = [$componentsDir];
        $config->cacheDir = $cacheDir;
        $config->env = Env::Dev;

        $resolver = new ComponentPathResolver($config);
        $compiler = new Compiler($config);

        $cardSource = $resolver->requireComponent('App\\Components\\CardFallback');
        $cardCompiled = $compiler->compileIfNeeded('App\\Components\\CardFallback', $cardSource);
        require $cardCompiled;

        $pageSource = $resolver->requireComponent('App\\Components\\PageFallback');
        $pageCompiled = $compiler->compileIfNeeded('App\\Components\\PageFallback', $pageSource);
        require $pageCompiled;

        self::assertStringContainsString('Header por defecto', file_get_contents($cardCompiled));
        self::assertStringContainsString('$this->header', file_get_contents($cardCompiled));
        self::assertStringContainsString('$this->body', file_get_contents($cardCompiled));

        $page = new \App\Components\PageFallback();

        $html = $page->render()->toHtml();

        self::assertSame(
            '<article><div class="card__header"><h1>Header por defecto</h1></div><div class="card__body"><p>Contenido principal</p></div></article>',
            $this->normalizeHtml($html),
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

    private function normalizeHtml(string $html): string
    {
        $html = trim($html);

        return preg_replace('/>\s+</', '><', $html) ?? $html;
    }
}