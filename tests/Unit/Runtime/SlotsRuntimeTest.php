<?php

declare(strict_types=1);

namespace PCP\Tests\Unit\Runtime;

use PCP\Component;
use PCP\Runtime\Node;
use PCP\Runtime\Runtime;
use PHPUnit\Framework\TestCase;

final class SlotsRuntimeTest extends TestCase
{
    public function test_named_slots_are_rendered_in_the_expected_regions(): void
    {
        $component = new CardLayoutComponent();

        $component->setChildren([
            Runtime::element('p', [], [
                Runtime::text('Contenido principal'),
            ]),
        ]);

        $component->setSlots([
            'default' => [
                Runtime::element('p', [], [
                    Runtime::text('Contenido principal'),
                ]),
            ],
            'header' => [
                Runtime::element('header', [], [
                    Runtime::text('Cabecera'),
                ]),
            ],
            'footer' => [
                Runtime::element('footer', [], [
                    Runtime::text('Pie'),
                ]),
            ],
        ]);

        $html = $component->render()->toHtml();

        self::assertSame(
            '<article><div class="card__header"><header>Cabecera</header></div><div class="card__body"><p>Contenido principal</p></div><div class="card__footer"><footer>Pie</footer></div></article>',
            $html,
        );
    }

    public function test_has_slot_returns_true_for_existing_named_slot(): void
    {
        $component = new CardLayoutComponent();

        $component->setSlots([
            'header' => [
                Runtime::text('Cabecera'),
            ],
        ]);

        self::assertTrue($component->hasSlot('header'));
        self::assertFalse($component->hasSlot('footer'));
    }

    public function test_slot_returns_empty_array_when_missing(): void
    {
        $component = new CardLayoutComponent();

        self::assertSame([], $component->slot('header'));
        self::assertSame([], $component->slot('default'));
    }

    public function test_default_slot_and_children_can_coexist_consistently(): void
    {
        $children = [
            Runtime::element('p', [], [
                Runtime::text('Hola'),
            ]),
        ];

        $component = new CardLayoutComponent();
        $component->setChildren($children);
        $component->setSlots([
            'default' => $children,
        ]);

        self::assertCount(1, $component->children());
        self::assertCount(1, $component->slot('default'));

        $html = Runtime::renderChildren($component->slot('default'))->toHtml();

        self::assertSame('<p>Hola</p>', $html);
    }
}

final class CardLayoutComponent extends Component
{
    public function render(): Node|string|int|float|bool|null
    {
        return Runtime::element('article', [], [
            Runtime::element('div', ['class' => 'card__header'], [
                Runtime::renderChildren($this->slot('header')),
            ]),
            Runtime::element('div', ['class' => 'card__body'], [
                Runtime::renderChildren($this->slot('default')),
            ]),
            Runtime::element('div', ['class' => 'card__footer'], [
                Runtime::renderChildren($this->slot('footer')),
            ]),
        ]);
    }
}