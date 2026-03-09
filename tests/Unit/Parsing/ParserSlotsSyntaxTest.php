<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Parsing;

use PCP\AST\FragmentNode;
use PCP\AST\NamedSlotUsageNode;
use PCP\AST\SlotOutletNode;
use PCP\Parsing\Parser;
use PCP\Parsing\Tokenizer;
use PHPUnit\Framework\TestCase;

final class ParserSlotsSyntaxTest extends TestCase
{
    public function test_parse_named_slot_usage_tag(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<Card\Header><h1>Hola</h1></Card\Header>');
        $ast = $parser->parse($tokens);

        self::assertInstanceOf(FragmentNode::class, $ast);
        self::assertCount(1, $ast->children);

        $node = $ast->children[0];

        self::assertInstanceOf(NamedSlotUsageNode::class, $node);
        self::assertSame('Card', $node->parentComponent);
        self::assertSame('header', $node->slotName);
        self::assertCount(1, $node->children);
    }

    public function test_parse_slot_outlet_tag(): void
    {
        $tokenizer = new Tokenizer();
        $parser = new Parser();

        $tokens = $tokenizer->tokenize('<Slot:Header>{ $this->header }</Slot:Header>');
        $ast = $parser->parse($tokens);

        self::assertInstanceOf(FragmentNode::class, $ast);
        self::assertCount(1, $ast->children);

        $node = $ast->children[0];

        self::assertInstanceOf(SlotOutletNode::class, $node);
        self::assertSame('header', $node->slotName);
    }
}