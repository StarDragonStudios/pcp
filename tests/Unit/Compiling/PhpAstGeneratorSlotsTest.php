<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Compiling;

use PCP\AST\ComponentNode;
use PCP\AST\FragmentNode;
use PCP\AST\NamedSlotUsageNode;
use PCP\AST\SlotOutletNode;
use PCP\AST\TextNode;
use PCP\Compiling\PhpAstGenerator;
use PHPUnit\Framework\TestCase;

final class PhpAstGeneratorSlotsTest extends TestCase
{
    public function test_generator_maps_named_slot_usage_to_named_node_props(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new ComponentNode(
                'Card',
                [],
                [
                    new NamedSlotUsageNode('Card', 'header', [
                        new TextNode('Cabecera'),
                    ]),
                    new NamedSlotUsageNode('Card', 'body', [
                        new TextNode('Contenido'),
                    ]),
                    new NamedSlotUsageNode('Card', 'footer', [
                        new TextNode('Pie'),
                    ]),
                ],
            ),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString("'header' =>", $php);
        self::assertStringContainsString("'body' =>", $php);
        self::assertStringContainsString("'footer' =>", $php);
        self::assertStringContainsString('Runtime::fragment', $php);
        self::assertStringContainsString('Runtime::component', $php);
    }

    public function test_generator_maps_regular_children_to_default_node_prop(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new ComponentNode(
                'Card',
                [],
                [
                    new TextNode('Contenido por defecto'),
                ],
            ),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString("'default' =>", $php);
        self::assertStringContainsString('Contenido por defecto', $php);
    }

    public function test_generator_emits_slot_outlet_as_property_lookup(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new SlotOutletNode('header'),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('$this->header', $php);
        self::assertStringContainsString('Runtime::fragment([])', $php);
    }

    public function test_generator_emits_default_slot_outlet_as_property_lookup(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new SlotOutletNode('default'),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('$this->default', $php);
        self::assertStringContainsString('Runtime::fragment([])', $php);
    }

    public function test_generator_emits_slot_outlet_with_fallback(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new SlotOutletNode('header', [
                new TextNode('Default header'),
            ]),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('$this->header', $php);
        self::assertStringContainsString('Default header', $php);
        self::assertStringContainsString('Runtime::fragment', $php);
    }

    public function test_generator_emits_default_slot_outlet_with_fallback(): void
    {
        $generator = new PhpAstGenerator();

        $ast = new FragmentNode([
            new SlotOutletNode('default', [
                new TextNode('Fallback default'),
            ]),
        ]);

        $php = $generator->generate($ast);

        self::assertStringContainsString('$this->default', $php);
        self::assertStringContainsString('Fallback default', $php);
        self::assertStringContainsString('Runtime::fragment', $php);
    }
}