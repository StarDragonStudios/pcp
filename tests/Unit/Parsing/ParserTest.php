<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Parsing;

use PCP\AST\ElementNode;
use PCP\AST\ExpressionNode;
use PCP\AST\FragmentNode;
use PCP\AST\IfNode;
use PCP\AST\TextNode;
use PCP\Parsing\Parser;
use PCP\Parsing\Tokenizer;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function test_parse_simple_element(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<h1>Hola</h1>');
        $ast = $parser->parse($tokens);

        self::assertInstanceOf(FragmentNode::class, $ast);
        self::assertCount(1, $ast->children);

        $element = $ast->children[0];

        self::assertInstanceOf(ElementNode::class, $element);
        self::assertSame('h1', $element->tag);
        self::assertCount(1, $element->children);

        $text = $element->children[0];

        self::assertInstanceOf(TextNode::class, $text);
        self::assertSame('Hola', $text->text);
    }

    public function test_parse_expression_inside_element(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<h1>{ $name }</h1>');
        $ast = $parser->parse($tokens);

        $element = $ast->children[0];

        self::assertInstanceOf(ElementNode::class, $element);

        $expr = $element->children[0];

        self::assertInstanceOf(ExpressionNode::class, $expr);
        self::assertSame('$name', $expr->expression);
    }

    public function test_parse_nested_elements(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<div><h1>Hola</h1></div>');
        $ast = $parser->parse($tokens);

        $div = $ast->children[0];

        self::assertInstanceOf(ElementNode::class, $div);
        self::assertSame('div', $div->tag);

        $h1 = $div->children[0];

        self::assertInstanceOf(ElementNode::class, $h1);
        self::assertSame('h1', $h1->tag);
    }

    public function test_parse_if_directive(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('@if ($logged)<p>Hola</p>@endif');
        $ast = $parser->parse($tokens);

        self::assertInstanceOf(FragmentNode::class, $ast);
        self::assertCount(1, $ast->children);

        $if = $ast->children[0];

        self::assertInstanceOf(IfNode::class, $if);
        self::assertSame('$logged', $if->condition);

        self::assertCount(1, $if->then);

        $p = $if->then[0];

        self::assertInstanceOf(ElementNode::class, $p);
        self::assertSame('p', $p->tag);
    }

    public function test_parse_fragment(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<><h1>Hola</h1></>');
        $ast = $parser->parse($tokens);

        self::assertInstanceOf(FragmentNode::class, $ast);
        self::assertCount(1, $ast->children);

        $fragment = $ast->children[0];

        self::assertInstanceOf(FragmentNode::class, $fragment);
        self::assertCount(1, $fragment->children);
    }
}