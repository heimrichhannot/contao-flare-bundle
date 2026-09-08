<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FormulaBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\AbstractPredicate;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterBuilderTest extends TestCase
{
    public function testRegistryLooksUpFilterTypesByClassName(): void
    {
        $type = new TestPredicate();
        $registry = new FilterPredicateRegistry([$type]);

        self::assertSame($type, $registry->get(TestPredicate::class));
        self::assertSame([TestPredicate::class => $type], $registry->all());
        self::assertNull($registry->get(UnknownPredicate::class));
    }

    public function testBuilderResolvesOptionsAndRecordsTargetedCalls(): void
    {
        $builder = new FormulaBuilder(
            new FilterPredicateRegistry([new TestPredicate()]),
            'main',
        );

        $builder
            ->add(TestPredicate::class, ['value' => 'first'])
            ->add(TestPredicate::class, ['value' => 'second', 'enabled' => true], 'translation');

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
        $builder = new FormulaBuilder(new FilterPredicateRegistry([]), 'main');

        $this->expectException(FilterException::class);
        $builder->add(TestPredicate::class, ['value' => 'test']);
    }

    public function testBuilderLetsOptionsResolverValidateRequiredOptions(): void
    {
        $builder = new FormulaBuilder(
            new FilterPredicateRegistry([new TestPredicate()]),
            'main',
        );

        $this->expectException(MissingOptionsException::class);
        $builder->add(TestPredicate::class);
    }

    public function testBuilderAbortThrowsAbortFilteringException(): void
    {
        $builder = new FormulaBuilder(new FilterPredicateRegistry([]), 'main');

        $this->expectException(AbortFilteringException::class);
        $builder->abort();
    }
}

final class TestPredicate extends AbstractPredicate
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('value')->required()->allowedTypes('string');
        $resolver->define('enabled')->default(false)->allowedTypes('bool');
    }

    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
    }
}

final class UnknownPredicate extends AbstractPredicate
{
    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
    }
}
