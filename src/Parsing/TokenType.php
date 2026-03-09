<?php

declare(strict_types=1);

namespace PCP\Parsing;

enum TokenType: string
{
    case Text = 'text';
    case OpenTag = 'open_tag';
    case CloseTag = 'close_tag';
    case SelfClosingTag = 'self_closing_tag';
    case Expression = 'expression';
    case Directive = 'directive';
}