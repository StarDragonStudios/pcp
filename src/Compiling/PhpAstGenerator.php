<?php

declare(strict_types=1);

namespace PCP\Compiling;

use PCP\AST\AttributeNode;
use PCP\AST\ComponentNode;
use PCP\AST\ElementNode;
use PCP\AST\ElseIfBranchNode;
use PCP\AST\ExpressionNode;
use PCP\AST\ForEachNode;
use PCP\AST\FragmentNode;
use PCP\AST\IfNode;
use PCP\AST\Node;
use PCP\AST\TextNode;
use RuntimeException;

final class PhpAstGenerator
{
    public function generate(Node $node): string
    {
        return $this->generateNode($node);
    }

    private function generateNode(Node $node): string
    {
        return match (true) {
            $node instanceof FragmentNode => $this->generateFragment($node),
            $node instanceof ElementNode => $this->generateElement($node),
            $node instanceof ComponentNode => $this->generateComponent($node),
            $node instanceof TextNode => $this->generateText($node),
            $node instanceof ExpressionNode => $this->generateExpression($node),
            $node instanceof IfNode => $this->generateIf($node),
            $node instanceof ForEachNode => $this->generateForEach($node),
            default => throw new RuntimeException(sprintf(
                'Unsupported AST node "%s".',
                $node::class,
            )),
        };
    }

    private function generateFragment(FragmentNode $node): string
    {
        $children = $this->generateChildrenArray($node->children);

        return "\\PCP\\Runtime\\Runtime::fragment({$children})";
    }

    private function generateElement(ElementNode $node): string
    {
        $tag = var_export($node->tag, true);
        $attributes = $this->generateAttributesArray($node->attributes);
        $children = $this->generateChildrenArray($node->children);

        return "\\PCP\\Runtime\\Runtime::element({$tag}, {$attributes}, {$children})";
    }

    private function generateComponent(ComponentNode $node): string
    {
        $componentClass = $this->normalizeComponentClass($node->component);

        $constructorArgs = $this->generateNamedArguments($node->props);

        [$defaultChildren, $slots] = $this->splitComponentChildrenIntoSlots($node->children);

        $childrenPhp = $this->generateChildrenArray($defaultChildren);
        $slotsPhp = $this->generateSlotsArray($slots);

        return <<<PHP
        (static function () {
            \$__pcp_component = new \\{$componentClass}({$constructorArgs});
        
            if (method_exists(\$__pcp_component, 'setChildren')) {
                \$__pcp_component->setChildren({$childrenPhp});
            }
        
            if (method_exists(\$__pcp_component, 'setSlots')) {
                \$__pcp_component->setSlots({$slotsPhp});
            }
        
            return \\PCP\\Runtime\\Runtime::normalizeChild(
                \$__pcp_component->render()
            );
        })()
        PHP;
    }

    private function generateText(TextNode $node): string
    {
        return "\\PCP\\Runtime\\Runtime::text(" . var_export($node->text, true) . ")";
    }

    private function generateExpression(ExpressionNode $node): string
    {
        return "\\PCP\\Runtime\\Runtime::normalizeChild({$node->expression})";
    }

    private function generateIf(IfNode $node): string
    {
        $php = "(static function (): \\PCP\\Runtime\\Node {\n";
        $php .= "    if ({$node->condition}) {\n";
        $php .= "        return " . $this->generateNodesAsFragmentOrSingle($node->then) . ";\n";
        $php .= "    }\n";

        foreach ($node->elseIfBranches as $branch) {
            if (!$branch instanceof ElseIfBranchNode) {
                throw new RuntimeException('Invalid elseif branch node.');
            }

            $php .= "    elseif ({$branch->condition}) {\n";
            $php .= "        return " . $this->generateNodesAsFragmentOrSingle($branch->body) . ";\n";
            $php .= "    }\n";
        }

        if ($node->else !== []) {
            $php .= "    else {\n";
            $php .= "        return " . $this->generateNodesAsFragmentOrSingle($node->else) . ";\n";
            $php .= "    }\n";
        } else {
            $php .= "    return \\PCP\\Runtime\\Runtime::fragment([]);\n";
        }

        $php .= "})()";

        return $php;
    }

    private function generateForEach(ForEachNode $node): string
    {
        $bodyExpr = $this->generateNodesAsFragmentOrSingle($node->body);

        return <<<PHP
        (static function (): \PCP\Runtime\Node {
            \$__pcp_children = [];
        
            foreach ($node->expression) {
                \$__pcp_children[] = $bodyExpr;
            }
        
            return \PCP\Runtime\Runtime::fragment(\$__pcp_children);
        })()
        PHP;
    }

    /**
     * @param list<AttributeNode> $attributes
     */
    private function generateAttributesArray(array $attributes): string
    {
        if ($attributes === []) {
            return '[]';
        }

        $parts = [];

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof AttributeNode) {
                throw new RuntimeException('Invalid attribute node.');
            }

            $parts[] = var_export($attribute->name, true) . ' => ' . $this->generateAttributeValue($attribute);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    private function generateAttributeValue(AttributeNode $attribute): string
    {
        return match (true) {
            is_string($attribute->value) => var_export($attribute->value, true),
            is_bool($attribute->value) => $attribute->value ? 'true' : 'false',
            $attribute->value instanceof ExpressionNode => '(' . $attribute->value->expression . ')',
            default => throw new RuntimeException(sprintf(
                'Unsupported attribute value for "%s".',
                $attribute->name,
            )),
        };
    }

    /**
     * @param list<Node> $children
     */
    private function generateChildrenArray(array $children): string
    {
        if ($children === []) {
            return '[]';
        }

        $parts = [];

        foreach ($children as $child) {
            $parts[] = $this->generateNode($child);
        }

        return '[' . implode(', ', $parts) . ']';
    }

    /**
     * @param list<Node> $nodes
     */
    private function generateNodesAsFragmentOrSingle(array $nodes): string
    {
        if ($nodes === []) {
            return '\\PCP\\Runtime\\Runtime::fragment([])';
        }

        if (count($nodes) === 1) {
            return $this->generateNode($nodes[0]);
        }

        return '\\PCP\\Runtime\\Runtime::fragment(' . $this->generateChildrenArray($nodes) . ')';
    }

    private function normalizeComponentClass(string $component): string
    {
        if (str_contains($component, '\\')) return ltrim($component, '\\');

        return 'App\\Components\\' . $component;
    }

    /**
     * @param list<AttributeNode> $attributes
     */
    private function generateNamedArguments(array $attributes): string
    {
        if ($attributes === []) return '';

        $parts = [];

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof AttributeNode)
                throw new RuntimeException('Invalid attribute node.');

            $parts[] =
                $attribute->name .
                ': ' .
                $this->generateAttributeValue($attribute);
        }

        return implode(', ', $parts);
    }

    /**
     * @param list<Node> $children
     * @return array{0: list<Node>, 1: array<string, list<Node>>}
     */
    private function splitComponentChildrenIntoSlots(array $children): array
    {
        $defaultChildren = [];
        $slots = [];

        foreach ($children as $child) {
            [$slotName, $normalizedChild] = $this->extractSlotFromChild($child);

            if ($slotName === null) {
                $defaultChildren[] = $normalizedChild;
                $slots['default'][] = $normalizedChild;
                continue;
            }

            $slots[$slotName][] = $normalizedChild;
        }

        if (!isset($slots['default'])) {
            $slots['default'] = $defaultChildren;
        }

        return [$defaultChildren, $slots];
    }

    /**
     * @return array{0:?string,1:Node}
     */
    private function extractSlotFromChild(Node $child): array
    {
        if ($child instanceof ElementNode) {
            $slotName = null;
            $attributes = [];

            foreach ($child->attributes as $attribute) {
                if (
                    $attribute instanceof AttributeNode &&
                    $attribute->name === 'slot' &&
                    is_string($attribute->value)
                ) {
                    $slotName = $attribute->value;
                    continue;
                }

                $attributes[] = $attribute;
            }

            if ($slotName !== null) {
                return [
                    $slotName,
                    new ElementNode($child->tag, $attributes, $child->children),
                ];
            }
        }

        if ($child instanceof ComponentNode) {
            $slotName = null;
            $props = [];

            foreach ($child->props as $attribute) {
                if (
                    $attribute instanceof AttributeNode &&
                    $attribute->name === 'slot' &&
                    is_string($attribute->value)
                ) {
                    $slotName = $attribute->value;
                    continue;
                }

                $props[] = $attribute;
            }

            if ($slotName !== null) {
                return [
                    $slotName,
                    new ComponentNode($child->component, $props, $child->children),
                ];
            }
        }

        return [null, $child];
    }

    /**
     * @param array<string, list<Node>> $slots
     */
    private function generateSlotsArray(array $slots): string
    {
        if ($slots === []) {
            return '[]';
        }

        $parts = [];

        foreach ($slots as $name => $children) {
            $parts[] = var_export($name, true) . ' => ' . $this->generateChildrenArray($children);
        }

        return '[' . implode(', ', $parts) . ']';
    }
}