<?php

declare(strict_types=1);

namespace PCP\Parsing;

use RuntimeException;

final class Tokenizer
{
    private int $length;
    private int $position = 0;

    public function tokenize(string $input): array
    {
        $this->length = strlen($input);
        $this->position = 0;

        $tokens = [];

        while (!$this->isAtEnd($input)) {
            if ($this->startsWith($input, '@')) {
                $tokens[] = $this->readDirective($input);
                continue;
            }

            if ($this->startsWith($input, '{')) {
                $tokens[] = $this->readExpression($input);
                continue;
            }

            if ($this->startsWith($input, '</')) {
                $tokens[] = $this->readCloseTag($input);
                continue;
            }

            if ($this->startsWith($input, '<')) {
                $tokens[] = $this->readOpenOrSelfClosingTag($input);
                continue;
            }

            $tokens[] = $this->readText($input);
        }

        return $this->mergeAdjacentTextTokens($tokens);
    }

    private function readDirective(string $input): Token
    {
        $offset = $this->position;
        $this->consumeChar($input); // @

        $name = $this->readIdentifier($input);

        $this->skipInlineWhitespace($input);

        $expression = null;

        if ($this->startsWith($input, '(')) {
            $expression = $this->readBalanced($input, '(', ')');
        }

        return new Token(TokenType::Directive, [
            'name' => $name,
            'expression' => $expression,
        ], $offset);
    }

    private function readExpression(string $input): Token
    {
        $offset = $this->position;
        $expression = $this->readBalanced($input, '{', '}');

        return new Token(TokenType::Expression, trim($expression), $offset);
    }

    private function readCloseTag(string $input): Token
    {
        $offset = $this->position;

        $this->consumeExact($input, '</');
        $this->skipInlineWhitespace($input);

        if ($this->startsWith($input, '>')) {
            $this->consumeChar($input);

            return new Token(TokenType::CloseTag, [
                'name' => '',
                'fragment' => true,
            ], $offset);
        }

        $name = $this->readTagName($input);

        $this->skipInlineWhitespace($input);
        $this->consumeExact($input, '>');

        return new Token(TokenType::CloseTag, [
            'name' => $name,
            'fragment' => false,
        ], $offset);
    }

    private function readOpenOrSelfClosingTag(string $input): Token
    {
        $offset = $this->position;

        $this->consumeChar($input); // <

        $this->skipInlineWhitespace($input);

        if ($this->startsWith($input, '>')) {
            $this->consumeChar($input);

            return new Token(TokenType::OpenTag, [
                'name' => '',
                'fragment' => true,
                'attributes' => [],
                'component' => false,
            ], $offset);
        }

        $name = $this->readTagName($input);
        $attributes = [];

        while (!$this->isAtEnd($input)) {
            $this->skipInlineWhitespace($input);

            if ($this->startsWith($input, '/>')) {
                $this->consumeExact($input, '/>');

                return new Token(TokenType::SelfClosingTag, [
                    'name' => $name,
                    'fragment' => false,
                    'attributes' => $attributes,
                    'component' => $this->isComponentName($name),
                ], $offset);
            }

            if ($this->startsWith($input, '>')) {
                $this->consumeChar($input);

                return new Token(TokenType::OpenTag, [
                    'name' => $name,
                    'fragment' => false,
                    'attributes' => $attributes,
                    'component' => $this->isComponentName($name),
                ], $offset);
            }

            $attributes[] = $this->readAttribute($input);
        }

        throw new RuntimeException(sprintf(
            'Unterminated tag starting at offset %d.',
            $offset,
        ));
    }

    private function readAttribute(string $input): array
    {
        $name = $this->readAttributeName($input);
        $this->skipInlineWhitespace($input);

        if (!$this->startsWith($input, '=')) {
            return [
                'name' => $name,
                'value' => true,
                'dynamic' => false,
            ];
        }

        $this->consumeChar($input); // =
        $this->skipInlineWhitespace($input);

        if ($this->startsWith($input, '"')) {
            return [
                'name' => $name,
                'value' => $this->readQuotedString($input, '"'),
                'dynamic' => false,
            ];
        }

        if ($this->startsWith($input, "'")) {
            return [
                'name' => $name,
                'value' => $this->readQuotedString($input, "'"),
                'dynamic' => false,
            ];
        }

        if ($this->startsWith($input, '{')) {
            return [
                'name' => $name,
                'value' => trim($this->readBalanced($input, '{', '}')),
                'dynamic' => true,
            ];
        }

        throw new RuntimeException(sprintf(
            'Invalid attribute value for "%s" at offset %d.',
            $name,
            $this->position,
        ));
    }

    private function readText(string $input): Token
    {
        $offset = $this->position;
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            if (
                $this->startsWith($input, '<') ||
                $this->startsWith($input, '{') ||
                $this->startsWith($input, '@')
            ) {
                break;
            }

            $buffer .= $this->consumeChar($input);
        }

        return new Token(TokenType::Text, $buffer, $offset);
    }

    private function readBalanced(string $input, string $open, string $close): string
    {
        if (!$this->startsWith($input, $open)) {
            throw new RuntimeException(sprintf(
                'Expected "%s" at offset %d.',
                $open,
                $this->position,
            ));
        }

        $this->consumeChar($input); // open
        $depth = 1;
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            $char = $this->consumeChar($input);

            if ($char === '"' || $char === "'") {
                $buffer .= $char;
                $buffer .= $this->readStringContents($input, $char);
                continue;
            }

            if ($char === $open) {
                $depth++;
                $buffer .= $char;
                continue;
            }

            if ($char === $close) {
                $depth--;

                if ($depth === 0) {
                    return $buffer;
                }

                $buffer .= $char;
                continue;
            }

            $buffer .= $char;
        }

        throw new RuntimeException(sprintf(
            'Unterminated balanced block "%s...%s".',
            $open,
            $close,
        ));
    }

    private function readQuotedString(string $input, string $quote): string
    {
        if (!$this->startsWith($input, $quote)) {
            throw new RuntimeException(sprintf(
                'Expected quoted string starting with %s at offset %d.',
                $quote,
                $this->position,
            ));
        }

        $this->consumeChar($input);

        return $this->readStringContents($input, $quote);
    }

    private function readStringContents(string $input, string $quote): string
    {
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            $char = $this->consumeChar($input);

            if ($char === '\\') {
                $buffer .= $char;

                if (!$this->isAtEnd($input)) {
                    $buffer .= $this->consumeChar($input);
                }

                continue;
            }

            if ($char === $quote) {
                return $buffer;
            }

            $buffer .= $char;
        }

        throw new RuntimeException(sprintf(
            'Unterminated string literal starting with %s.',
            $quote,
        ));
    }

    private function readIdentifier(string $input): string
    {
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            $char = $this->currentChar($input);

            if (!preg_match('/[A-Za-z_]/', $char) && $buffer === '') {
                throw new RuntimeException(sprintf(
                    'Expected identifier at offset %d.',
                    $this->position,
                ));
            }

            if (!preg_match('/[A-Za-z0-9_]/', $char)) {
                break;
            }

            $buffer .= $this->consumeChar($input);
        }

        return $buffer;
    }

    private function readTagName(string $input): string
    {
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            $char = $this->currentChar($input);

            if (!preg_match('/[A-Za-z0-9:_\\\\-]/', $char)) break;

            $buffer .= $this->consumeChar($input);
        }

        if ($buffer === '') {
            throw new RuntimeException(sprintf(
                'Expected tag name at offset %d.',
                $this->position,
            ));
        }

        return $buffer;
    }

    private function readAttributeName(string $input): string
    {
        $buffer = '';

        while (!$this->isAtEnd($input)) {
            $char = $this->currentChar($input);

            if (!preg_match('/[A-Za-z0-9:_-]/', $char)) {
                break;
            }

            $buffer .= $this->consumeChar($input);
        }

        if ($buffer === '') {
            throw new RuntimeException(sprintf(
                'Expected attribute name at offset %d.',
                $this->position,
            ));
        }

        return $buffer;
    }

    private function skipInlineWhitespace(string $input): void
    {
        while (!$this->isAtEnd($input)) {
            $char = $this->currentChar($input);

            if ($char !== ' ' && $char !== "\t" && $char !== "\n" && $char !== "\r") {
                break;
            }

            $this->position++;
        }
    }

    private function mergeAdjacentTextTokens(array $tokens): array
    {
        $merged = [];

        foreach ($tokens as $token) {
            $last = $merged[count($merged) - 1] ?? null;

            if (
                $token->type === TokenType::Text &&
                $last instanceof Token &&
                $last->type === TokenType::Text
            ) {
                $merged[count($merged) - 1] = new Token(
                    TokenType::Text,
                    $last->value . $token->value,
                    $last->offset,
                );

                continue;
            }

            $merged[] = $token;
        }

        return $merged;
    }

    private function isComponentName(string $name): bool
    {
        return $name !== '' && preg_match('/^[A-Z]/', $name) === 1;
    }

    private function startsWith(string $input, string $needle): bool
    {
        return substr($input, $this->position, strlen($needle)) === $needle;
    }

    private function consumeExact(string $input, string $expected): void
    {
        if (!$this->startsWith($input, $expected)) {
            throw new RuntimeException(sprintf(
                'Expected "%s" at offset %d.',
                $expected,
                $this->position,
            ));
        }

        $this->position += strlen($expected);
    }

    private function consumeChar(string $input): string
    {
        $char = $input[$this->position] ?? null;

        if ($char === null) {
            throw new RuntimeException('Unexpected end of input.');
        }

        $this->position++;

        return $char;
    }

    private function currentChar(string $input): string
    {
        $char = $input[$this->position] ?? null;

        if ($char === null) {
            throw new RuntimeException('Unexpected end of input.');
        }

        return $char;
    }

    private function isAtEnd(string $input): bool
    {
        return $this->position >= $this->length;
    }
}