<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Runtime;

use PCP\Component;
use PCP\Runtime\Node;
use PCP\Runtime\Runtime;
use PHPUnit\Framework\TestCase;

final class RuntimeComponentTest extends TestCase
{
    /**
     * @throws \JsonException
     */
    public function test_component_renders_basic_component(): void
    {
        $node = Runtime::component(BasicGreetingComponent::class, [
            'name' => 'Rodrigo',
        ]);

        self::assertInstanceOf(Node::class, $node);
        self::assertSame(
            '<h1>Hola Rodrigo</h1>',
            $node->toHtml(),
        );
    }

    /**
     * @throws \JsonException
     */
    public function test_component_passes_children_to_component(): void
    {
        $node = Runtime::component(ChildrenEchoComponent::class, [], [
            Runtime::element('span', [], [
                Runtime::text('Uno'),
            ]),
            Runtime::element('span', [], [
                Runtime::text('Dos'),
            ]),
        ]);

        self::assertSame(
            '<div><span>Uno</span><span>Dos</span></div>',
            $node->toHtml(),
        );
    }

    /**
     * @throws \JsonException
     */
    public function test_component_supports_default_slot(): void
    {
        $node = Runtime::component(SlotEchoComponent::class, [], [
            Runtime::element('p', [], [
                Runtime::text('Contenido'),
            ]),
        ]);

        self::assertSame(
            '<section><p>Contenido</p></section>',
            $node->toHtml(),
        );
    }

    /**
     * @throws \JsonException
     */
    public function test_component_throws_for_missing_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PCP component class "App\\Components\\MissingComponent" does not exist.');

        Runtime::component('App\\Components\\MissingComponent');
    }

    /**
     * @throws \JsonException
     */
    public function test_component_throws_for_non_component_class(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf(
            'PCP component class "%s" must extend %s.',
            NotAComponent::class,
            Component::class,
        ));

        Runtime::component(NotAComponent::class);
    }
}

final class BasicGreetingComponent extends Component
{
    public function __construct(
        public string $name,
    ) {
        parent::__construct();
    }

    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('h1', [], [
            Runtime::text('Hola ' . $this->name),
        ]);
    }
}

final class ChildrenEchoComponent extends Component
{
    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('div', [], $this->children());
    }
}

final class SlotEchoComponent extends Component
{
    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('section', [], [
            Runtime::renderChildren($this->slot('default')),
        ]);
    }
}

final class NotAComponent
{
}