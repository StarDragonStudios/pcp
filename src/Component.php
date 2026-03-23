<?php

declare(strict_types=1);

namespace PCP;

use PCP\Runtime\Node;
use ReflectionClass;
use ReflectionException;

abstract class Component
{
    protected array $state = [];

    /**
     * @var list<Node|string|int|float|bool|null>
     */
    protected array $children = [];

    /**
     * Node props implícitas/nombradas inyectadas por PCP.
     *
     * @var array<string, Node|null>
     */
    private array $__pcpNodeProps = [];

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
     * @return list<Node|string|int|float|bool|null>
     */
    public function children(): array
    {
        return $this->children;
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
     * Inyecta node props nombradas:
     * - si existe una propiedad real compatible, la rellena por reflection
     * - si no existe, queda disponible vía __get/__isset
     *
     * @param array<string, Node|null> $nodeProps
     */
    public function __pcpSetNodeProps(array $nodeProps): static
    {
        foreach ($nodeProps as $name => $value) {
            $this->__pcpNodeProps[$name] = $value;
            $this->hydrateDeclaredPropertyIfPresent($name, $value);
        }

        return $this;
    }

    public function __get(string $name): mixed
    {
        return $this->__pcpNodeProps[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->__pcpNodeProps)
            && $this->__pcpNodeProps[$name] !== null;
    }

    /**
     * Props serializables para runtime cliente/islas.
     *
     * @return array<string, mixed>
     */
    public function props(): array
    {
        $vars = get_object_vars($this);

        unset(
            $vars['state'],
            $vars['children'],
            $vars['__pcpNodeProps'],
        );

        return $vars;
    }

    abstract public function render(): Node|string|int|float|bool|null;

    private function hydrateDeclaredPropertyIfPresent(string $name, ?Node $value): void
    {
        try {
            $reflection = new ReflectionClass($this);

            while ($reflection !== false) {
                if ($reflection->hasProperty($name)) {
                    $property = $reflection->getProperty($name);
                    $property->setAccessible(true);
                    $property->setValue($this, $value);

                    return;
                }

                $reflection = $reflection->getParentClass();
            }
        } catch (ReflectionException) {
            // Si falla reflection por cualquier razón, mantenemos el valor
            // en $__pcpNodeProps y seguirá funcionando vía __get/__isset.
        }
    }
}