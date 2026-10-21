<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Predicate;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use HeimrichHannot\FlareBundle\Filter\Predicate\DcaSelectPredicate;
use HeimrichHannot\FlareBundle\Query\FilterConditionsBuilder;
use PHPUnit\Framework\TestCase;

final class DcaSelectPredicateTest extends TestCase
{
    public function testOptionsSharingALabelStaySelectableByKey(): void
    {
        $builder = $this->createBuilder();

        (new DcaSelectPredicate())->buildConditions($builder, [
            'field' => 'status',
            'selected' => ['a', 'b'],
            'valid_options' => ['a' => 'Same', 'b' => 'Same', 'c' => 'Other'],
            'is_multiple_dca_field' => false,
        ]);

        self::assertCount(1, $builder->getConditions());
        self::assertSame(['values' => ['a', 'b']], $builder->getParameters());
    }

    private function createBuilder(): FilterConditionsBuilder
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('createExpressionBuilder')->willReturn(new ExpressionBuilder($connection));
        $connection->method('quoteIdentifier')->willReturnCallback(static fn (string $id): string => "`{$id}`");

        return new FilterConditionsBuilder($connection, 'main');
    }
}
