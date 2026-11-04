<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

final class ControlContextTest extends TestCase
{
    private function list(): ListSpec
    {
        return new ListSpec(driver: $this->createStub(ListDriverInterface::class), type: 'test_list', dc: 'tl_test');
    }

    private static function filter(bool $withControl = true, ?FilterElementInterface $element = null): Filter
    {
        return new Filter(
            element: new ElementBinding($element ?? new StubFilterElement(), 'test_element', new ComponentConfig([
                'label' => 'element',
            ]), null),
            alias: 'test',
            control: $withControl
                ? new ControlBinding(new KeywordsFilterControl(), 'test_control', new ComponentConfig([
                    'label' => 'control',
                ]))
                : null,
        );
    }

    public function testRejectsAFilterWithoutControl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ControlContext($this->list(), self::filter(withControl: false), new ConfigurationIntent());
    }

    public function testGetReadsTheControlConfig(): void
    {
        $context = new ControlContext($this->list(), self::filter(), new ConfigurationIntent());

        self::assertSame('control', $context->get('label'));
        self::assertTrue($context->has('label'));
    }

    public function testForElementKeepsListFilterAndIntent(): void
    {
        $context = new ControlContext(
            $list = $this->list(),
            $filter = self::filter(),
            $intent = new ConfigurationIntent(),
        );

        $elementContext = $context->forElement();

        self::assertSame($list, $elementContext->list);
        self::assertSame($filter, $elementContext->filter);
        self::assertSame($intent, $elementContext->intent);
        self::assertSame('element', $elementContext->get('label'));
    }

    public function testElementWithoutContractReturnsTheElement(): void
    {
        $element = new StubFilterElement();
        $context = new ControlContext($this->list(), self::filter(element: $element), new ConfigurationIntent());

        self::assertSame($element, $context->element());
    }

    public function testElementReturnsTheElementAsTheContract(): void
    {
        $element = new ContextBoundsElement();
        $context = new ControlContext($this->list(), self::filter(element: $element), new ConfigurationIntent());

        self::assertSame($element, $context->element(DateBoundsContract::class));
    }

    public function testElementThrowsForAMissingContract(): void
    {
        $context = new ControlContext($this->list(), self::filter(), new ConfigurationIntent());

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage(\sprintf(
            'The control "test_control" requires an element implementing %s, got %s.',
            DateBoundsContract::class,
            StubFilterElement::class,
        ));

        $context->element(DateBoundsContract::class);
    }

    public function testTryElementReturnsTheElementAsTheContract(): void
    {
        $element = new ContextBoundsElement();
        $context = new ControlContext($this->list(), self::filter(element: $element), new ConfigurationIntent());

        self::assertSame($element, $context->tryElement(DateBoundsContract::class));
    }

    public function testTryElementReturnsNullForAMissingContract(): void
    {
        $context = new ControlContext($this->list(), self::filter(), new ConfigurationIntent());

        self::assertNull($context->tryElement(DateBoundsContract::class));
    }
}

final class ContextBoundsElement implements FilterElementInterface, DateBoundsContract
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {}

    public function getDateBounds(ElementContext $context): DateBounds
    {
        return new DateBounds();
    }
}
