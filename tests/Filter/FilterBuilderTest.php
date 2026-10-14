<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\Type\AbstractFilterType;
use HeimrichHannot\FlareBundle\Query\FilterQueryBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterTypeRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterBuilderTest extends TestCase
{
    public function testRegistryLooksUpFilterTypesByClassName(): void
    {
        $type = new TestFilterType();
        $registry = new FilterTypeRegistry([$type]);

        self::assertSame($type, $registry->get(TestFilterType::class));
        self::assertSame([TestFilterType::class => $type], $registry->all());
        self::assertNull($registry->get(UnknownFilterType::class));
    }

    public function testBuilderResolvesOptionsAndRecordsTargetedCalls(): void
    {
        $builder = new FilterBuilder(
            new FilterTypeRegistry([new TestFilterType()]),
            'main',
        );

        $builder
            ->add(TestFilterType::class, ['value' => 'first'])
            ->add(TestFilterType::class, ['value' => 'second', 'enabled' => true], 'translation');

        $calls = $builder->all();

        self::assertCount(2, $calls);
        self::assertSame('main', $calls[0]->targetAlias);
        self::assertSame('first', $calls[0]->options['value']);
        self::assertFalse($calls[0]->options['enabled']);
        self::assertSame('translation', $calls[1]->targetAlias);
        self::assertSame('second', $calls[1]->options['value']);
        self::assertTrue($calls[1]->options['enabled']);
    }

    public function testBuilderRejectsUnknownFilterTypes(): void
    {
        $builder = new FilterBuilder(new FilterTypeRegistry([]), 'main');

        $this->expectException(FilterException::class);
        $builder->add(TestFilterType::class, ['value' => 'test']);
    }

    public function testBuilderLetsOptionsResolverValidateRequiredOptions(): void
    {
        $builder = new FilterBuilder(
            new FilterTypeRegistry([new TestFilterType()]),
            'main',
        );

        $this->expectException(MissingOptionsException::class);
        $builder->add(TestFilterType::class);
    }

    public function testBuilderAbortThrowsAbortFilteringException(): void
    {
        $builder = new FilterBuilder(new FilterTypeRegistry([]), 'main');

        $this->expectException(AbortFilteringException::class);
        $builder->abort();
    }
}

final class TestFilterType extends AbstractFilterType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('value')->required()->allowedTypes('string');
        $resolver->define('enabled')->default(false)->allowedTypes('bool');
    }

    public function buildQuery(FilterQueryBuilder $builder, array $options): void
    {
    }
}

final class UnknownFilterType extends AbstractFilterType
{
}
