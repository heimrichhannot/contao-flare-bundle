<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

final class ElementScopeTest extends TestCase
{
    private function scope(): ElementScope
    {
        return new ElementScope(
            list: new ListSpec(driver: $this->createStub(ListDriverInterface::class), type: 'test_list', dc: 'tl_test'),
            filter: new Filter(
                element: new ElementBinding(new StubFilterElement(), 'test_element', new ComponentConfig([
                    'label' => 'element',
                    'field' => 'title',
                ])),
                alias: 'test',
                control: new ControlBinding(new KeywordsFilterControl(), 'test_control', new ComponentConfig([
                    'label' => 'control',
                ])),
            ),
            engineContext: new ConfigurationContext(),
        );
    }

    public function testGetReadsTheElementConfig(): void
    {
        self::assertSame('element', $this->scope()->get('label'));
        self::assertSame('title', $this->scope()->get('field'));
    }

    public function testGetThrowsForUndefinedKey(): void
    {
        $this->expectException(UndefinedConfigKeyError::class);

        $this->scope()->get('placeholder');
    }

    public function testHasMirrorsTheElementConfig(): void
    {
        self::assertTrue($this->scope()->has('field'));
        self::assertFalse($this->scope()->has('placeholder'));
    }
}
