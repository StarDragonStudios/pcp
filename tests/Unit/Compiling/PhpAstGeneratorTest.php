<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Compiling;

use PCP\AST\ElementNode;
use PCP\AST\ExpressionNode;
use PCP\AST\FragmentNode;
use PCP\AST\TextNode;
use PCP\Compiling\PhpAstGenerator;
use PHPUnit\Framework\TestCase;

final class PhpAstGeneratorTest extends TestCase
{
    public function test_generate_simple_element(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new ElementNode('h1', [], [
                new TextNode('Hola'),
            ]),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('Runtime::element', $php);
        self::assertStringContainsString("'h1'", $php);
        self::assertStringContainsString('Runtime::text', $php);
        self::assertStringContainsString("'Hola'", $php);
    }

    public function test_generate_expression(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new ElementNode('h1', [], [
                new ExpressionNode('$name'),
            ]),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('$name', $php);
        self::assertStringContainsString('Runtime::normalizeChild', $php);
    }

    public function test_generate_nested_elements(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new ElementNode('div', [], [
                new ElementNode('h1', [], [
                    new TextNode('Hola'),
                ]),
            ]),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString("'div'", $php);
        self::assertStringContainsString("'h1'", $php);
        self::assertStringContainsString("'Hola'", $php);
    }
}