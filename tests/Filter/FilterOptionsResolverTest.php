<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Contract\OptionsContract;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterOptionsResolverTest extends TestCase
{
    public function testResolvesOptionsThroughElementSchema(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());
        $element = new ElementConfigAwareElement();

        $config = $resolver->resolve(new Filter(element: $element, type: 'test', config: ['field' => 'title']), $element);

        self::assertSame('title', $config['field']);
        self::assertFalse($config['intrinsic']);
    }

    public function testReturnsOptionsVerbatimWithoutOptionsContract(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());
        $element = new PlainElement();

        $config = ['anything' => 'goes', 'unvalidated' => true];

        self::assertSame($config, $resolver->resolve(new Filter(element: $element, type: 'test', config: $config), $element));
    }

    public function testWrapsSchemaViolationsInFilterException(): void
    {
        $resolver = new FilterOptionsResolver(new SchemaResolver());
        $element = new ElementConfigAwareElement();
        $filter = new Filter(element: $element, type: 'test', config: ['unknown_key' => 1], source: 'tl_flare_filter.42');

        try
        {
            $resolver->resolve($filter, $element);
            self::fail('Expected FilterException.');
        }
        catch (FilterException $e)
        {
            self::assertStringContainsString(ElementConfigAwareElement::class, $e->getMessage());
            self::assertSame('tl_flare_filter.42', $e->getSource());
        }
    }
}

final class ElementConfigAwareElement implements FilterElementInterface, OptionsContract
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('intrinsic')->default(false)->allowedTypes('bool');
        $resolver->define('field')->default(null)->allowedTypes('string', 'null');
    }

    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
    }
}

final class PlainElement implements FilterElementInterface
{
    public function buildForm(FormBuilderInterface $builder, FilterContext $context): void
    {
    }

    public function buildFilter(FilterBuilderInterface $builder, FilterContext $context, array $values): void
    {
    }
}
