<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Exception;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use PHPUnit\Framework\TestCase;

final class FilterExceptionTest extends TestCase
{
    public function testWrapsAnyOtherExceptionWithTheFiltersSourceAndTheMethod(): void
    {
        $cause = new \RuntimeException('connection lost', 7);

        $wrapped = FilterException::wrap($cause, $this->filter('tl_flare_filter.42'), 'Control::buildForm');

        self::assertSame('connection lost', $wrapped->getMessage());
        self::assertSame(7, $wrapped->getCode());
        self::assertSame($cause, $wrapped->getPrevious());
        self::assertSame('Control::buildForm', $wrapped->getMethod());
        self::assertSame('tl_flare_filter.42', $wrapped->getSource());

        $framework = new FlareException('not a filter failure');
        self::assertSame($framework, FilterException::wrap($framework, $this->filter(null), 'm')->getPrevious());
        self::assertSame('filter inlined', FilterException::wrap($cause, $this->filter(null), 'm')->getSource());
    }

    public function testAFilterExceptionIsKeptAndOnlyGetsTheMissingSourceAndMethod(): void
    {
        $own = new FilterException('rejected');

        self::assertSame($own, FilterException::wrap($own, $this->filter('tl_flare_filter.42'), 'Control::decode'));
        self::assertSame('tl_flare_filter.42', $own->getSource());
        self::assertSame('Control::decode', $own->getMethod());

        $located = new FilterException('rejected', method: 'Port::buildChoices', source: 'elsewhere');
        FilterException::wrap($located, $this->filter('tl_flare_filter.42'), 'Control::buildForm');

        self::assertSame('elsewhere', $located->getSource());
        self::assertSame('Port::buildChoices', $located->getMethod());
    }

    private function filter(?string $source): Filter
    {
        return new Filter(
            element: new ElementBinding(
                $this->createMock(FilterElementInterface::class),
                'test_element',
                new ComponentConfig(),
            ),
            alias: 'test',
            source: $source,
        );
    }
}
