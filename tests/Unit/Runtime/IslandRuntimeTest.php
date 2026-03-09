<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Runtime;

use JsonException;
use PCP\Component;
use PCP\Island;
use PCP\Runtime\Node;
use PCP\Runtime\Runtime;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IslandRuntimeTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function test_island_wraps_ssr_with_metadata(): void
    {
        $node = Runtime::component(IslandGreetingComponent::class, [
            'name' => 'Rodrigo',
        ]);

        self::assertInstanceOf(Node::class, $node);

        $html = $node->toHtml();

        self::assertStringContainsString('data-pcp-island="' . IslandGreetingComponent::class . '"', $html);
        self::assertStringContainsString('data-pcp-strategy="load"', $html);
        self::assertStringContainsString('data-pcp-props=', $html);
        self::assertStringContainsString('<h1>Hola Rodrigo</h1>', $html);
    }

    /**
     * @throws JsonException
     */
    public function test_island_serializes_props_to_json(): void
    {
        $node = Runtime::component(IslandGreetingComponent::class, [
            'name' => 'Ana',
        ]);

        $html = $node->toHtml();

        self::assertStringContainsString('&quot;name&quot;:&quot;Ana&quot;', $html);
    }

    /**
     * @throws JsonException
     */
    public function test_island_respects_strategy(): void
    {
        $node = Runtime::component(VisibleIslandComponent::class, [
            'name' => 'Luis',
        ]);

        $html = $node->toHtml();

        self::assertStringContainsString('data-pcp-strategy="visible"', $html);
    }

    /**
     * @throws JsonException
     */
    public function test_island_throws_if_prop_not_serializable(): void
    {
        $this->expectException(RuntimeException::class);

        Runtime::component(IslandInvalidPropsComponent::class, [
            'obj' => new \stdClass(),
        ]);
    }
}

/**
 * Simple island component.
 */
#[Island]
final class IslandGreetingComponent extends Component
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

/**
 * Island with custom strategy.
 */
#[Island(strategy: 'visible')]
final class VisibleIslandComponent extends Component
{
    public function __construct(
        public string $name,
    ) {
        parent::__construct();
    }

    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('p', [], [
            Runtime::text('Hola ' . $this->name),
        ]);
    }
}

/**
 * Island with invalid prop.
 */
#[Island]
final class IslandInvalidPropsComponent extends Component
{
    public function __construct(
        public object $obj,
    ) {
        parent::__construct();
    }

    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('div', [], [
            Runtime::text('Invalid'),
        ]);
    }
}