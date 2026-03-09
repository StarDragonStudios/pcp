<?php

declare(strict_types=1);

namespace PCP;

use PCP\Runtime\Node;

abstract class Component
{
    protected array $state = [];

    /**
     * @var list<Node|string|int|float|bool|null>
     */
    protected array $children = [];

    /**
     * @var array<string, list<Node|string|int|float|bool|null>>
     */
    protected array $slots = [];

    public function __construct(array $state = [])
    {
        $this->state = $state;
    }

    /**
     * @param list<Node|string|int|float|bool|null> $children
     */
    public function setChildren(array $children): static
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @param array<string, list<Node|string|int|float|bool|null>> $slots
     */
    public function setSlots(array $slots): static
    {
        $this->slots = $slots;
        return $this;
    }

    /**
     * @return list<Node|string|int|float|bool|null>
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @return list<Node|string|int|float|bool|null>
     */
    public function slot(string $name = 'default'): array
    {
        return $this->slots[$name] ?? [];
    }

    public function hasSlot(string $name = 'default'): bool
    {
        return ($this->slots[$name] ?? []) !== [];
    }

    public function state(): array
    {
        return $this->state;
    }

    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    public function setState(string $key, mixed $value): static
    {
        $this->state[$key] = $value;
        return $this;
    }

    public function mergeState(array $state): static
    {
        $this->state = array_replace($this->state, $state);
        return $this;
    }

    /**
     * Props serializables para runtime cliente/islas.
     *
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $vars = get_object_vars($this);

        unset($vars['state'], $vars['children'], $vars['slots']);

        return $vars;
    }

    abstract public function render(): Node|string|int|float|bool|null;
}