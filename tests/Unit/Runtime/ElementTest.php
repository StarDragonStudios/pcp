<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Runtime;

use PCP\Runtime\Element;
use PCP\Runtime\Runtime;
use PHPUnit\Framework\TestCase;

final class ElementTest extends TestCase
{
    public function test_regular_element_renders_open_and_close_tags(): void
    {
        $element = new Element('div', [], [
            Runtime::text('Hola'),
        ]);

        self::assertSame(
            '<div>Hola</div>',
            $element->toHtml(),
        );
    }

    public function test_void_element_renders_without_closing_tag(): void
    {
        $element = new Element('input', [
            'type' => 'text',
            'value' => 'hola',
        ]);

        self::assertSame(
            '<input type="text" value="hola">',
            $element->toHtml(),
        );
    }

    public function test_void_element_ignores_children(): void
    {
        $element = new Element('img', [
            'src' => '/logo.png',
            'alt' => 'Logo',
        ], [
            Runtime::text('esto no debería salir'),
        ]);

        self::assertSame(
            '<img src="/logo.png" alt="Logo">',
            $element->toHtml(),
        );
    }

    public function test_boolean_attributes_render_correctly(): void
    {
        $element = new Element('input', [
            'disabled' => true,
            'required' => true,
            'hidden' => false,
        ]);

        self::assertSame(
            '<input disabled required>',
            $element->toHtml(),
        );
    }

    public function test_attributes_are_escaped(): void
    {
        $element = new Element('meta', [
            'content' => '"hola" & adiós',
        ]);

        self::assertSame(
            '<meta content="&quot;hola&quot; &amp; adiós">',
            $element->toHtml(),
        );
    }
}