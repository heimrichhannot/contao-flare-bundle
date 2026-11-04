<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

final class ElementContextTest extends TestCase
{
    private function context(): ElementContext
    {
        return new ElementContext(
            list: new ListCompound(
                driver: $this->createStub(ListDriverInterface::class),
                type: 'test_list',
                dc: 'tl_test',
            ),
            filter: new FilterCompound(
                element: new ElementBinding(new StubFilterElement(), 'test_element', new ComponentConfig([
                    'label' => 'element',
                    'field' => 'title',
                ]), null),
                alias: 'test',
                control: new ControlBinding(new KeywordsFilterControl(), 'test_control', new ComponentConfig([
                    'label' => 'control',
                ])),
            ),
            intent: new ConfigurationIntent(),
        );
    }

    public function testGetReadsTheElementConfig(): void
    {
        self::assertSame('element', $this->context()->get('label'));
        self::assertSame('title', $this->context()->get('field'));
    }

    public function testGetThrowsForUndefinedKey(): void
    {
        $this->expectException(UndefinedConfigKeyError::class);

        $this->context()->get('placeholder');
    }

    public function testHasMirrorsTheElementConfig(): void
    {
        self::assertTrue($this->context()->has('field'));
        self::assertFalse($this->context()->has('placeholder'));
    }
}
