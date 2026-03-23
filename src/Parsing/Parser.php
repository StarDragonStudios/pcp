<?php

declare(strict_types=1);

namespace PCP\Parsing;

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

final class Parser
{
    /**
     * @var list<Token>
     */
    private array $tokens = [];

    private int $position = 0;

    /**
     * @param list<Token> $tokens
     */
    public function parse(array $tokens): FragmentNode
    {
        $this->tokens = array_values($tokens);
        $this->position = 0;

        $children = $this->parseNodesUntil();

        if (!$this->isAtEnd()) {
            throw new RuntimeException(sprintf(
                'Unexpected token "%s" at offset %d.',
                $this->current()->type->value,
                $this->current()->offset,
            ));
        }

        return new FragmentNode($children);
    }

    /**
     * @return list<Node>
     */
    private function parseNodesUntil(?callable $stop = null): array
    {
        $nodes = [];

        while (!$this->isAtEnd()) {
            if ($stop !== null && $stop($this->current())) {
                break;
            }

            $nodes[] = $this->parseNode();
        }

        return $nodes;
    }

    private function parseNode(): Node
    {
        $token = $this->current();

        return match ($token->type) {
            TokenType::Text => $this->parseText(),
            TokenType::Expression => $this->parseExpression(),
            TokenType::OpenTag => $this->parseOpenTag(),
            TokenType::SelfClosingTag => $this->parseSelfClosingTag(),
            TokenType::Directive => $this->parseDirective(),
            TokenType::CloseTag => throw new RuntimeException(sprintf(
                'Unexpected closing tag at offset %d.',
                $token->offset,
            )),
        };
    }

    private function parseText(): TextNode
    {
        $token = $this->consume(TokenType::Text);

        return new TextNode($token->value);
    }

    private function parseExpression(): ExpressionNode
    {
        $token = $this->consume(TokenType::Expression);

        return new ExpressionNode($token->value);
    }

    private function parseOpenTag(): Node
    {
        $token = $this->consume(TokenType::OpenTag);

        $name = $token->value['name'];
        $fragment = $token->value['fragment'];
        $component = $token->value['component'];
        $attributes = $this->mapAttributes($token->value['attributes']);

        if ($fragment) {
            $children = $this->parseNodesUntil(function (Token $token): bool {
                return $token->type === TokenType::CloseTag
                    && ($token->value['fragment'] ?? false) === true;
            });

            $this->consumeFragmentCloseTag();

            return new FragmentNode($children);
        }

        if ($this->isSlotOutletTag($name)) {
            $children = $this->parseNodesUntil(function (Token $token) use ($name): bool {
                return $token->type === TokenType::CloseTag
                    && ($token->value['fragment'] ?? false) === false
                    && ($token->value['name'] ?? null) === $name;
            });

            $this->consumeNamedCloseTag($name);

            return new SlotOutletNode(
                $this->normalizeSlotName(substr($name, strlen('Slot:'))),
                $children,
            );
        }

        if ($this->isNamedSlotUsageTag($name)) {
            [$parentComponent, $slotName] = $this->splitNamedSlotUsageTag($name);

            $children = $this->parseNodesUntil(function (Token $token) use ($name): bool {
                return $token->type === TokenType::CloseTag
                    && ($token->value['fragment'] ?? false) === false
                    && ($token->value['name'] ?? null) === $name;
            });

            $this->consumeNamedCloseTag($name);

            return new NamedSlotUsageNode(
                $parentComponent,
                $this->normalizeSlotName($slotName),
                $children,
            );
        }

        $children = $this->parseNodesUntil(function (Token $token) use ($name): bool {
            return $token->type === TokenType::CloseTag
                && ($token->value['fragment'] ?? false) === false
                && ($token->value['name'] ?? null) === $name;
        });

        $this->consumeNamedCloseTag($name);

        if ($component) {
            return new ComponentNode($name, $attributes, $children);
        }

        return new ElementNode($name, $attributes, $children);
    }

    private function parseSelfClosingTag(): Node
    {
        $token = $this->consume(TokenType::SelfClosingTag);

        $name = $token->value['name'];
        $fragment = $token->value['fragment'];
        $component = $token->value['component'];
        $attributes = $this->mapAttributes($token->value['attributes']);

        if ($fragment) {
            return new FragmentNode([]);
        }

        if ($this->isSlotOutletTag($name)) {
            return new SlotOutletNode(
                $this->normalizeSlotName(substr($name, strlen('Slot:'))),
                [],
            );
        }

        if ($this->isNamedSlotUsageTag($name)) {
            [$parentComponent, $slotName] = $this->splitNamedSlotUsageTag($name);

            return new NamedSlotUsageNode(
                $parentComponent,
                $this->normalizeSlotName($slotName),
                [],
            );
        }

        if ($component) {
            return new ComponentNode($name, $attributes, []);
        }

        return new ElementNode($name, $attributes, []);
    }

    private function parseDirective(): Node
    {
        $token = $this->current();
        $name = $token->value['name'];

        return match ($name) {
            'if' => $this->parseIfDirective(),
            'foreach' => $this->parseForEachDirective(),
            'else', 'elseif', 'endif', 'endforeach' => throw new RuntimeException(sprintf(
                'Unexpected directive "@%s" at offset %d.',
                $name,
                $token->offset,
            )),
            default => throw new RuntimeException(sprintf(
                'Unsupported directive "@%s" at offset %d.',
                $name,
                $token->offset,
            )),
        };
    }

    private function parseIfDirective(): IfNode
    {
        $ifToken = $this->consumeDirective('if');
        $condition = $ifToken->value['expression'];

        if (!is_string($condition) || trim($condition) === '') {
            throw new RuntimeException(sprintf(
                'Directive "@if" requires a condition at offset %d.',
                $ifToken->offset,
            ));
        }

        $then = $this->parseNodesUntil(function (Token $token): bool {
            return $token->type === TokenType::Directive
                && in_array($token->value['name'], ['elseif', 'else', 'endif'], true);
        });

        $elseIfBranches = [];

        while ($this->isCurrentDirective('elseif')) {
            $elseIfToken = $this->consumeDirective('elseif');
            $elseIfCondition = $elseIfToken->value['expression'];

            if (!is_string($elseIfCondition) || trim($elseIfCondition) === '') {
                throw new RuntimeException(sprintf(
                    'Directive "@elseif" requires a condition at offset %d.',
                    $elseIfToken->offset,
                ));
            }

            $body = $this->parseNodesUntil(function (Token $token): bool {
                return $token->type === TokenType::Directive
                    && in_array($token->value['name'], ['elseif', 'else', 'endif'], true);
            });

            $elseIfBranches[] = new ElseIfBranchNode($elseIfCondition, $body);
        }

        $else = [];

        if ($this->isCurrentDirective('else')) {
            $this->consumeDirective('else');

            $else = $this->parseNodesUntil(function (Token $token): bool {
                return $token->type === TokenType::Directive
                    && $token->value['name'] === 'endif';
            });
        }

        $this->consumeDirective('endif');

        return new IfNode($condition, $then, $elseIfBranches, $else);
    }

    private function parseForEachDirective(): ForEachNode
    {
        $foreachToken = $this->consumeDirective('foreach');
        $expression = $foreachToken->value['expression'];

        if (!is_string($expression) || trim($expression) === '') {
            throw new RuntimeException(sprintf(
                'Directive "@foreach" requires an expression at offset %d.',
                $foreachToken->offset,
            ));
        }

        $body = $this->parseNodesUntil(function (Token $token): bool {
            return $token->type === TokenType::Directive
                && $token->value['name'] === 'endforeach';
        });

        $this->consumeDirective('endforeach');

        return new ForEachNode($expression, $body);
    }

    /**
     * @param list<array{name:string, value:mixed, dynamic:bool}> $attributes
     * @return list<AttributeNode>
     */
    private function mapAttributes(array $attributes): array
    {
        $mapped = [];

        foreach ($attributes as $attribute) {
            $value = $attribute['value'];

            if ($attribute['dynamic'] === true) {
                $value = new ExpressionNode((string) $value);
            }

            $mapped[] = new AttributeNode($attribute['name'], $value);
        }

        return $mapped;
    }

    private function consume(TokenType $expected): Token
    {
        $token = $this->current();

        if ($token->type !== $expected) {
            throw new RuntimeException(sprintf(
                'Expected token "%s", got "%s" at offset %d.',
                $expected->value,
                $token->type->value,
                $token->offset,
            ));
        }

        $this->position++;

        return $token;
    }

    private function consumeDirective(string $name): Token
    {
        $token = $this->current();

        if (
            $token->type !== TokenType::Directive ||
            ($token->value['name'] ?? null) !== $name
        ) {
            throw new RuntimeException(sprintf(
                'Expected directive "@%s" at offset %d.',
                $name,
                $token->offset,
            ));
        }

        $this->position++;

        return $token;
    }

    private function consumeFragmentCloseTag(): void
    {
        $token = $this->current();

        if (
            $token->type !== TokenType::CloseTag ||
            ($token->value['fragment'] ?? false) !== true
        ) {
            throw new RuntimeException(sprintf(
                'Expected fragment closing tag "</>" at offset %d.',
                $token->offset,
            ));
        }

        $this->position++;
    }

    private function consumeNamedCloseTag(string $name): void
    {
        $token = $this->current();

        if (
            $token->type !== TokenType::CloseTag ||
            ($token->value['fragment'] ?? false) !== false ||
            ($token->value['name'] ?? null) !== $name
        ) {
            throw new RuntimeException(sprintf(
                'Expected closing tag "</%s>" at offset %d.',
                $name,
                $token->offset,
            ));
        }

        $this->position++;
    }

    private function isCurrentDirective(string $name): bool
    {
        if ($this->isAtEnd()) {
            return false;
        }

        $token = $this->current();

        return $token->type === TokenType::Directive
            && ($token->value['name'] ?? null) === $name;
    }

    private function current(): Token
    {
        $token = $this->tokens[$this->position] ?? null;

        if (!$token instanceof Token) {
            throw new RuntimeException('Unexpected end of token stream.');
        }

        return $token;
    }

    private function isAtEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }

    private function isNamedSlotUsageTag(string $name): bool
    {
        return str_contains($name, '\\');
    }

    private function isSlotOutletTag(string $name): bool
    {
        return str_starts_with($name, 'Slot:');
    }

    /**
     * @return array{0:string,1:string}
     */
    private function splitNamedSlotUsageTag(string $name): array
    {
        $parts = explode('\\', $name, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException(sprintf(
                'Invalid named slot usage tag "%s".',
                $name,
            ));
        }

        return [$parts[0], $parts[1]];
    }

    private function normalizeSlotName(string $name): string
    {
        return lcfirst($name);
    }
}