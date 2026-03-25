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
use PCP\AST\NamedSlotUsageNode;
use PCP\AST\Node;
use PCP\AST\SlotOutletNode;
use PCP\AST\TextNode;
use RuntimeException;

final class PhpAstGenerator
{
    private string $currentNamespace = '';

    public function setNamespace(string $namespace): void
    {
        $this->currentNamespace = $namespace;
    }

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
            $node instanceof SlotOutletNode => $this->generateSlotOutlet($node),
            $node instanceof NamedSlotUsageNode => throw new RuntimeException(sprintf(
                'Named slot usage node "%s\%s" cannot be generated outside a parent component context.',
                $node->parentComponent,
                $node->slotName,
            )),
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
        $componentClass = var_export($this->normalizeComponentClass($node->component), true);
        [$namedNodeProps, $regularChildren] = $this->splitNamedNodeProps($node);

        $props = $this->generateAttributesArray($node->props);
        $children = $this->generateChildrenArray($regularChildren);
        $nodeProps = $this->generateNamedNodePropsArray($namedNodeProps);

        return "\\PCP\\Runtime\\Runtime::component({$componentClass}, {$props}, {$children}, {$nodeProps})";
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

    foreach ({$node->expression}) {
        \$__pcp_children[] = {$bodyExpr};
    }

    return \PCP\Runtime\Runtime::fragment(\$__pcp_children);
})()
PHP;
    }

    private function generateSlotOutlet(SlotOutletNode $node): string
    {
        $property = '$this->' . $this->normalizeSlotPropertyName($node->slotName);

        if ($node->fallbackChildren === []) {
            return "(($property) ?? \\PCP\\Runtime\\Runtime::fragment([]))";
        }

        return "(($property) ?? \\PCP\\Runtime\\Runtime::fragment(" . $this->generateChildrenArray($node->fallbackChildren) . "))";
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
     * @param array<string, list<Node>> $namedNodeProps
     */
    private function generateNamedNodePropsArray(array $namedNodeProps): string
    {
        if ($namedNodeProps === []) {
            return '[]';
        }

        $parts = [];

        foreach ($namedNodeProps as $name => $children) {
            $parts[] =
                var_export($name, true)
                . ' => '
                . '\\PCP\\Runtime\\Runtime::fragment(' . $this->generateChildrenArray($children) . ')';
        }

        return '[' . implode(', ', $parts) . ']';
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

    /**
     * @return array{0: array<string, list<Node>>, 1: list<Node>}
     */
    private function splitNamedNodeProps(ComponentNode $component): array
    {
        $namedNodeProps = [];
        $regularChildren = [];

        foreach ($component->children as $child) {
            if (!$child instanceof NamedSlotUsageNode) {
                $regularChildren[] = $child;
                continue;
            }

            if ($child->parentComponent !== $component->component) {
                throw new RuntimeException(sprintf(
                    'Named slot <%s\\%s> must be a direct child of <%s>.',
                    $child->parentComponent,
                    ucfirst($child->slotName),
                    $component->component,
                ));
            }

            $namedNodeProps[$this->normalizeSlotPropertyName($child->slotName)] = $child->children;
        }

        if ($regularChildren !== []) {
            $namedNodeProps['default'] = $regularChildren;
        }

        return [$namedNodeProps, $regularChildren];
    }

    private function normalizeSlotPropertyName(string $slotName): string
    {
        return lcfirst($slotName);
    }

    private function normalizeComponentClass(string $component): string
    {
        if (str_contains($component, '\\')) {
            return ltrim($component, '\\');
        }

        if ($this->currentNamespace !== '') {
            return $this->currentNamespace . '\\' . $component;
        }

        return 'App\\Components\\' . $component;
    }
}