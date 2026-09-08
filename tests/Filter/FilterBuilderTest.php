<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\FilterBuilder;
use HeimrichHannot\FlareBundle\Filter\Logic\AbstractFilterLogic;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterLogicRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterBuilderTest extends TestCase
{
    public function testRegistryLooksUpFilterTypesByClassName(): void
    {
        $type = new TestFilterLogic();
        $registry = new FilterLogicRegistry([$type]);

        self::assertSame($type, $registry->get(TestFilterLogic::class));
        self::assertSame([TestFilterLogic::class => $type], $registry->all());
        self::assertNull($registry->get(UnknownFilterLogic::class));
    }

    public function testBuilderResolvesOptionsAndRecordsTargetedCalls(): void
    {
        $builder = new FilterBuilder(
            new FilterLogicRegistry([new TestFilterLogic()]),
            'main',
        );

        $builder
            ->add(TestFilterLogic::class, ['value' => 'first'])
            ->add(TestFilterLogic::class, ['value' => 'second', 'enabled' => true], 'translation');

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
        $builder = new FilterBuilder(new FilterLogicRegistry([]), 'main');

        $this->expectException(FilterException::class);
        $builder->add(TestFilterLogic::class, ['value' => 'test']);
    }

    public function testBuilderLetsOptionsResolverValidateRequiredOptions(): void
    {
        $builder = new FilterBuilder(
            new FilterLogicRegistry([new TestFilterLogic()]),
            'main',
        );

        $this->expectException(MissingOptionsException::class);
        $builder->add(TestFilterLogic::class);
    }

    public function testBuilderAbortThrowsAbortFilteringException(): void
    {
        $builder = new FilterBuilder(new FilterLogicRegistry([]), 'main');

        $this->expectException(AbortFilteringException::class);
        $builder->abort();
    }
}

final class TestFilterLogic extends AbstractFilterLogic
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

final class UnknownFilterLogic extends AbstractFilterLogic
{
    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
    }
}
