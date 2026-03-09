<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Parsing;

use PCP\Parsing\TokenType;
use PCP\Parsing\Tokenizer;
use PHPUnit\Framework\TestCase;

final class TokenizerTest extends TestCase
{
    public function test_tokenize_simple_element_with_text(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize('<h1>Hola</h1>');

        self::assertCount(3, $tokens);

        self::assertSame(TokenType::OpenTag, $tokens[0]->type);
        self::assertSame('h1', $tokens[0]->value['name']);

        self::assertSame(TokenType::Text, $tokens[1]->type);
        self::assertSame('Hola', $tokens[1]->value);

        self::assertSame(TokenType::CloseTag, $tokens[2]->type);
        self::assertSame('h1', $tokens[2]->value['name']);
    }

    public function test_tokenize_expression_inside_element(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize('<h1>Hola { $name }</h1>');

        self::assertCount(4, $tokens);

        self::assertSame(TokenType::OpenTag, $tokens[0]->type);

        self::assertSame(TokenType::Text, $tokens[1]->type);
        self::assertSame('Hola ', $tokens[1]->value);

        self::assertSame(TokenType::Expression, $tokens[2]->type);
        self::assertSame('$name', $tokens[2]->value);

        self::assertSame(TokenType::CloseTag, $tokens[3]->type);
    }

    public function test_tokenize_directive(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize('@if ($logged)<p>Hola</p>@endif');

        self::assertCount(5, $tokens);

        self::assertSame(TokenType::Directive, $tokens[0]->type);
        self::assertSame('if', $tokens[0]->value['name']);
        self::assertSame('$logged', $tokens[0]->value['expression']);

        self::assertSame(TokenType::OpenTag, $tokens[1]->type);
        self::assertSame('p', $tokens[1]->value['name']);

        self::assertSame(TokenType::Text, $tokens[2]->type);
        self::assertSame('Hola', $tokens[2]->value);

        self::assertSame(TokenType::CloseTag, $tokens[3]->type);
        self::assertSame('p', $tokens[3]->value['name']);

        self::assertSame(TokenType::Directive, $tokens[4]->type);
        self::assertSame('endif', $tokens[4]->value['name']);
        self::assertNull($tokens[4]->value['expression']);
    }

    public function test_tokenize_attributes_static_dynamic_and_boolean(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize(
            '<input type="text" value={$query} disabled />'
        );

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::SelfClosingTag, $tokens[0]->type);

        $attributes = $tokens[0]->value['attributes'];

        self::assertCount(3, $attributes);

        self::assertSame('type', $attributes[0]['name']);
        self::assertSame('text', $attributes[0]['value']);
        self::assertFalse($attributes[0]['dynamic']);

        self::assertSame('value', $attributes[1]['name']);
        self::assertSame('$query', $attributes[1]['value']);
        self::assertTrue($attributes[1]['dynamic']);

        self::assertSame('disabled', $attributes[2]['name']);
        self::assertTrue($attributes[2]['value']);
        self::assertFalse($attributes[2]['dynamic']);
    }

    public function test_tokenize_component_tag_marks_component_true(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize('<UserCard name="Rodrigo" />');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::SelfClosingTag, $tokens[0]->type);
        self::assertSame('UserCard', $tokens[0]->value['name']);
        self::assertTrue($tokens[0]->value['component']);
    }

    public function test_tokenize_fragment(): void
    {
        $tokenizer = new Tokenizer();

        $tokens = $tokenizer->tokenize('<><h1>Hola</h1></>');

        self::assertCount(5, $tokens);

        self::assertSame(TokenType::OpenTag, $tokens[0]->type);
        self::assertTrue($tokens[0]->value['fragment']);

        self::assertSame(TokenType::OpenTag, $tokens[1]->type);
        self::assertSame('h1', $tokens[1]->value['name']);

        self::assertSame(TokenType::Text, $tokens[2]->type);
        self::assertSame('Hola', $tokens[2]->value);

        self::assertSame(TokenType::CloseTag, $tokens[3]->type);
        self::assertSame('h1', $tokens[3]->value['name']);

        self::assertSame(TokenType::CloseTag, $tokens[4]->type);
        self::assertTrue($tokens[4]->value['fragment']);
    }
}