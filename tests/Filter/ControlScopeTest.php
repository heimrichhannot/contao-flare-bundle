<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBounds;
use HeimrichHannot\FlareBundle\Contract\FilterElement\DateBoundsContract;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

final class ControlScopeTest extends TestCase
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
            ])),
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

        new ControlScope($this->list(), self::filter(withControl: false), new ConfigurationContext());
    }

    public function testGetReadsTheControlConfig(): void
    {
        $scope = new ControlScope($this->list(), self::filter(), new ConfigurationContext());

        self::assertSame('control', $scope->get('label'));
        self::assertTrue($scope->has('label'));
    }

    public function testForElementKeepsListFilterAndContext(): void
    {
        $scope = new ControlScope($list = $this->list(), $filter = self::filter(), $context = new ConfigurationContext());

        $elementScope = $scope->forElement();

        self::assertSame($list, $elementScope->list);
        self::assertSame($filter, $elementScope->filter);
        self::assertSame($context, $elementScope->engineContext);
        self::assertSame('element', $elementScope->get('label'));
    }

    public function testElementWithoutContractReturnsTheElement(): void
    {
        $element = new StubFilterElement();
        $scope = new ControlScope($this->list(), self::filter(element: $element), new ConfigurationContext());

        self::assertSame($element, $scope->element());
    }

    public function testElementReturnsTheElementAsTheContract(): void
    {
        $element = new ScopeBoundsElement();
        $scope = new ControlScope($this->list(), self::filter(element: $element), new ConfigurationContext());

        self::assertSame($element, $scope->element(DateBoundsContract::class));
    }

    public function testElementThrowsForAMissingContract(): void
    {
        $scope = new ControlScope($this->list(), self::filter(), new ConfigurationContext());

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage(\sprintf(
            'The control "test_control" requires an element implementing %s, got %s.',
            DateBoundsContract::class,
            StubFilterElement::class,
        ));

        $scope->element(DateBoundsContract::class);
    }

    public function testTryElementReturnsTheElementAsTheContract(): void
    {
        $element = new ScopeBoundsElement();
        $scope = new ControlScope($this->list(), self::filter(element: $element), new ConfigurationContext());

        self::assertSame($element, $scope->tryElement(DateBoundsContract::class));
    }

    public function testTryElementReturnsNullForAMissingContract(): void
    {
        $scope = new ControlScope($this->list(), self::filter(), new ConfigurationContext());

        self::assertNull($scope->tryElement(DateBoundsContract::class));
    }
}

final class ScopeBoundsElement implements FilterElementInterface, DateBoundsContract
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {}

    public function getDateBounds(ElementScope $scope): DateBounds
    {
        return new DateBounds();
    }
}
