<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Predicate;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\TimeWindowPredicate;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TimeWindowPredicateTest extends TestCase
{
    private const START_OK = '`main`.`startTime` IS NOT NULL AND `main`.`startTime` <> 0';
    private const END_OK = '(`main`.`endTime` IS NULL OR `main`.`endTime` = 0'
        . ' OR `main`.`endTime` >= `main`.`startTime`)';
    private const OVERLAP = '`main`.`startTime` <= :stop'
        . ' AND CASE WHEN `main`.`endTime` IS NULL OR `main`.`endTime` = 0'
        . ' THEN `main`.`startTime` ELSE `main`.`endTime` END >= :start';
    private const RECURRENCE = '`main`.`recurring` IS NOT NULL AND `main`.`recurring` <> 0'
        . ' AND `main`.`startTime` <= :stop AND (COALESCE(`main`.`recurrences`, 0) = 0'
        . ' OR (`main`.`recurrences` > 0 AND `main`.`repeatEnd` >= :start))';

    public function testTheStartLiesInThePeriod(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ((`main`.`startTime` BETWEEN :start AND :stop))',
            ['start_field' => 'startTime'],
        );
    }

    public function testAnEndBeforeTheStartNeverMatches(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ' . self::END_OK . ' AND ((`main`.`startTime` BETWEEN :start AND :stop))',
            ['start_field' => 'startTime', 'stop_field' => 'endTime'],
        );
    }

    public function testOverlapComparesTheEffectiveEnd(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ' . self::END_OK . ' AND ((' . self::OVERLAP . '))',
            ['start_field' => 'startTime', 'stop_field' => 'endTime', 'overlap' => true],
        );
    }

    public function testOverlapNeedsAnEndColumn(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ((`main`.`startTime` BETWEEN :start AND :stop))',
            ['start_field' => 'startTime', 'overlap' => true],
        );
    }

    public function testRecurrenceAddsContaosApproximation(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ((`main`.`startTime` BETWEEN :start AND :stop) OR (' . self::RECURRENCE . '))',
            [
                'start_field' => 'startTime',
                'recurring_field' => 'recurring',
                'recurrences_field' => 'recurrences',
                'repeat_end_field' => 'repeatEnd',
            ],
        );
    }

    public function testOverlapAndRecurrenceTogether(): void
    {
        $this->assertCondition(
            self::START_OK . ' AND ' . self::END_OK . ' AND ((' . self::OVERLAP . ') OR (' . self::RECURRENCE . '))',
            [
                'start_field' => 'startTime',
                'stop_field' => 'endTime',
                'overlap' => true,
                'recurring_field' => 'recurring',
                'recurrences_field' => 'recurrences',
                'repeat_end_field' => 'repeatEnd',
            ],
        );
    }

    public function testCastColumnsAreReadAsNumbers(): void
    {
        $s = 'CAST(`main`.`s` AS SIGNED)';
        $e = 'CAST(`main`.`e` AS SIGNED)';

        $this->assertCondition(
            "{$s} IS NOT NULL AND {$s} <> 0 AND ({$e} IS NULL OR {$e} = 0 OR {$e} >= {$s})"
            . " AND (({$s} <= :stop AND CASE WHEN {$e} IS NULL OR {$e} = 0 THEN {$s} ELSE {$e} END >= :start))",
            ['start_field' => 's', 'stop_field' => 'e', 'overlap' => true, 'cast_fields' => ['s', 'e']],
        );
    }

    public function testParametersAreBoundAsIntegers(): void
    {
        $builder = $this->buildConditions(['start_field' => 'startTime']);

        self::assertSame(
            ['start' => ParameterType::INTEGER, 'stop' => ParameterType::INTEGER],
            $builder->build(null)->getTypes(),
        );
    }

    public function testPartialRecurrenceIsRejected(): void
    {
        $resolver = new OptionsResolver();
        (new TimeWindowPredicate())->configureOptions($resolver);

        $this->expectException(InvalidOptionsException::class);

        $resolver->resolve(['start' => 1, 'stop' => 2, 'start_field' => 's', 'recurring_field' => 'r']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function assertCondition(string $expectedSql, array $options): void
    {
        $builder = $this->buildConditions($options);

        self::assertSame([$expectedSql], $builder->getConditions());
        self::assertSame(['start' => 100, 'stop' => 200], $builder->getParameters());
    }

    /**
     * @param array<string, mixed> $options Resolved through the predicate's options, with the period 100–200.
     */
    private function buildConditions(array $options): FilterConditionsBuilder
    {
        $predicate = new TimeWindowPredicate();

        $resolver = new OptionsResolver();
        $predicate->configureOptions($resolver);

        $builder = $this->createBuilder();
        $predicate->buildConditions($builder, $resolver->resolve(['start' => 100, 'stop' => 200] + $options));

        return $builder;
    }

    private function createBuilder(): FilterConditionsBuilder
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));
        $connection->method('quoteIdentifier')->willReturnCallback((new MySQLPlatform())->quoteIdentifier(...));

        return new FilterConditionsBuilder($connection, 'main');
    }
}
