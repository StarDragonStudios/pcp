<?php

declare(strict_types=1);

namespace PCP\Runtime;

use JsonException;
use PCP\Component;
use PCP\Core\ComponentMetadataResolver;
use RuntimeException;

final class Runtime
{
    /**
     * @param list<Node|string|int|float|bool|null> $children
     */
    public static function fragment(array $children = []): Fragment
    {
        return new Fragment($children);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<Node|string|int|float|bool|null> $children
     */
    public static function element(string $tag, array $attributes = [], array $children = []): Element
    {
        return new Element($tag, $attributes, $children);
    }

    public static function text(string|int|float|bool|null $value): Text
    {
        return new Text((string) ($value ?? ''));
    }

    public static function raw(string $html): Raw
    {
        return new Raw($html);
    }

    /**
     * @param array<string, mixed> $props
     * @param list<Node|string|int|float|bool|null> $children
     * @throws JsonException
     */
    public static function component(string $component, array $props = [], array $children = []): Node
    {
        if (!class_exists($component)) {
            throw new RuntimeException(sprintf(
                'PCP component class "%s" does not exist.',
                $component,
            ));
        }

        if (!is_subclass_of($component, Component::class)) {
            throw new RuntimeException(sprintf(
                'PCP component class "%s" must extend %s.',
                $component,
                Component::class,
            ));
        }

        $instance = new $component(...$props);

        if (method_exists($instance, 'setChildren')) {
            $instance->setChildren($children);
        }

        if (method_exists($instance, 'setSlots')) {
            $instance->setSlots([
                'default' => $children,
            ]);
        }

        $metadata = new ComponentMetadataResolver()->resolve($component);

        if ($metadata->clientSide) {
            throw new RuntimeException(sprintf(
                'Pure client-side PCP component "%s" cannot be rendered directly on the server yet.',
                $component,
            ));
        }

        $rendered = self::normalizeChild($instance->render());

        if (!$metadata->island) {
            return $rendered;
        }

        return self::renderIsland(
            component: $component,
            instance: $instance,
            rendered: $rendered,
            strategy: $metadata->islandStrategy ?? 'load',
        );
    }

    public static function normalizeChild(mixed $value): Node
    {
        if ($value instanceof Node) {
            return $value;
        }

        if ($value === null || $value === false) {
            return new Text('', false);
        }

        if (is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return new Text((string) $value, true);
        }

        throw new RuntimeException(sprintf(
            'Unsupported PCP child value of type "%s".',
            get_debug_type($value),
        ));
    }

    /**
     * @throws JsonException
     */
    private static function renderIsland(
        string $component,
        Component $instance,
        Node $rendered,
        string $strategy,
    ): Node {
        $props = $instance->props();
        self::assertSerializableProps($props, $component);

        $json = json_encode(
            $props,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        $attrs = [
            'data-pcp-island' => $component,
            'data-pcp-strategy' => $strategy,
            'data-pcp-props' => $json,
        ];

        return new Element('div', $attrs, [$rendered]);
    }

    /**
     * @param array<string, mixed> $props
     */
    private static function assertSerializableProps(array $props, string $component): void
    {
        foreach ($props as $key => $value) {
            if (!self::isSerializableValue($value)) {
                throw new RuntimeException(sprintf(
                    'PCP island component "%s" has a non-serializable prop "%s" of type "%s".',
                    $component,
                    $key,
                    get_debug_type($value),
                ));
            }
        }
    }

    private static function isSerializableValue(mixed $value): bool
    {
        if (
            $value === null ||
            is_string($value) ||
            is_int($value) ||
            is_float($value) ||
            is_bool($value)
        ) {
            return true;
        }

        if (is_array($value)) {
            return array_all($value, fn($item) => self::isSerializableValue($item));

        }

        return false;
    }

    /**
     * @param list<Node|string|int|float|bool|null> $children
     */
    public static function renderChildren(array $children): Node
    {
        return self::fragment($children);
    }
}