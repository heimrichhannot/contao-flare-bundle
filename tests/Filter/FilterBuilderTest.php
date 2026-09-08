<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\LogicSequencer;
use HeimrichHannot\FlareBundle\Filter\Logic\AbstractLogic;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use HeimrichHannot\FlareBundle\Registry\FilterLogicRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterBuilderTest extends TestCase
{
    public function testRegistryLooksUpFilterTypesByClassName(): void
    {
        $type = new TestLogic();
        $registry = new FilterLogicRegistry([$type]);

        self::assertSame($type, $registry->get(TestLogic::class));
        self::assertSame([TestLogic::class => $type], $registry->all());
        self::assertNull($registry->get(UnknownLogic::class));
    }

    public function testBuilderResolvesOptionsAndRecordsTargetedCalls(): void
    {
        $builder = new LogicSequencer(
            new FilterLogicRegistry([new TestLogic()]),
            'main',
        );

        $builder
            ->add(TestLogic::class, ['value' => 'first'])
            ->add(TestLogic::class, ['value' => 'second', 'enabled' => true], 'translation');

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
        $builder = new LogicSequencer(new FilterLogicRegistry([]), 'main');

        $this->expectException(FilterException::class);
        $builder->add(TestLogic::class, ['value' => 'test']);
    }

    public function testBuilderLetsOptionsResolverValidateRequiredOptions(): void
    {
        $builder = new LogicSequencer(
            new FilterLogicRegistry([new TestLogic()]),
            'main',
        );

        $this->expectException(MissingOptionsException::class);
        $builder->add(TestLogic::class);
    }

    public function testBuilderAbortThrowsAbortFilteringException(): void
    {
        $builder = new LogicSequencer(new FilterLogicRegistry([]), 'main');

        $this->expectException(AbortFilteringException::class);
        $builder->abort();
    }
}

final class TestLogic extends AbstractLogic
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

final class UnknownLogic extends AbstractLogic
{
    public function buildConditions(FilterConditionsBuilder $builder, array $options): void
    {
    }
}
