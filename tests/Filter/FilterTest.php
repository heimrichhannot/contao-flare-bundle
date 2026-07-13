<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Element\CallbackFilterElement;
use HeimrichHannot\FlareBundle\Filter\Type\AbstractFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use HeimrichHannot\FlareBundle\Specification\ListSpecification;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterTest extends TestCase
{
    public function testElementUnionAccessors(): void
    {
        $typed = new Filter(element: 'flare_bool');

        self::assertSame('flare_bool', $typed->getElementType());
        self::assertNull($typed->getElementInstance());

        $instance = new CallbackFilterElement(static function (): void {});
        $inline = new Filter(element: $instance);

        self::assertNull($inline->getElementType());
        self::assertSame($instance, $inline->getElementInstance());
    }

    public function testWithersPreserveOtherFields(): void
    {
        $filter = new Filter(element: 'test', config: ['a' => 1], alias: 'foo', source: 'tl_flare_filter.1');

        $withData = $filter->withData(['value' => 42]);

        self::assertNull($filter->data);
        self::assertSame(['value' => 42], $withData->data);
        self::assertSame('foo', $withData->alias);
        self::assertSame(['a' => 1], $withData->config);
        self::assertSame('tl_flare_filter.1', $withData->source);

        $targeted = $filter->withTargetAlias('translation');

        self::assertSame('translation', $targeted->targetAlias);
        self::assertTrue($targeted->targetingForced);
        self::assertFalse($filter->targetingForced);
    }

    public function testFromTypeBuildsSingleFilterCall(): void
    {
        $filter = Filter::fromType(RecordingFilterType::class, ['value' => 'x']);

        $element = $filter->getElementInstance();
        self::assertNotNull($element);

        $builder = new FilterBuilder(new FilterTypeRegistry([new RecordingFilterType()]), 'main');
        $context = $this->createContext($filter);

        $element->buildFilter($builder, $context, []);

        $calls = $builder->all();
        self::assertCount(1, $calls);
        self::assertSame(RecordingFilterType::class, $calls[0]->typeClass);
        self::assertSame('x', $calls[0]->options['value']);
    }

    public function testFromCallbackForcesTargetAlias(): void
    {
        $filter = Filter::fromCallback(static function (): void {}, targetAlias: 'translation');

        self::assertSame('translation', $filter->targetAlias);
        self::assertTrue($filter->targetingForced);
    }

    public function testFingerprintRepresentsInlineElementsByClass(): void
    {
        $filter = Filter::fromCallback(static function (): void {});

        self::assertSame(CallbackFilterElement::class, $filter->fingerprint()['element']);
    }

    private function createContext(Filter $filter): FilterContext
    {
        return new FilterContext(
            list: new ListSpecification('test_list', 'tl_test'),
            filter: $filter,
            config: $filter->config,
            engineContext: new class implements ContextInterface {
                public static function getContextType(): string
                {
                    return 'test';
                }
            },
        );
    }
}

final class RecordingFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('value')->required()->allowedTypes('string');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
    }
}
