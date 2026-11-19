<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\DataContainer;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;

final class FilterListTableResolverTest extends TestCase
{
    /** @var list<array{string, array<mixed>}> */
    private array $queries = [];

    public function testAFixedTableDriverWinsOverAnEmptyDc(): void
    {
        $resolver = $this->resolver([7 => ['type' => 'fixed', 'dc' => '']]);

        self::assertSame('tl_fixed', $resolver->resolve(7));
    }

    public function testTheStoredDcIsUsed(): void
    {
        $resolver = $this->resolver([7 => ['type' => 'stored', 'dc' => 'tl_stored']]);

        self::assertSame('tl_stored', $resolver->resolve(7));
        self::assertSame([[
            'SELECT l.type, l.dc FROM tl_flare_filter f INNER JOIN tl_flare_list l ON l.id = f.pid WHERE f.id = ?',
            [7],
        ]], $this->queries);
    }

    public function testNoRowNoListOrNoDriverIsNull(): void
    {
        $resolver = $this->resolver([
            7 => ['type' => 'unknown', 'dc' => 'tl_stored'],
            8 => ['type' => 'stored', 'dc' => ''],
        ]);

        // No filter row, or a filter whose list is gone (the join finds no row).
        self::assertNull($resolver->resolve(6));
        // No driver registered under the list's type.
        self::assertNull($resolver->resolve(7));
        // The driver resolves no table.
        self::assertNull($resolver->resolve(8));
    }

    /**
     * @param array<int, array{type: string, dc: string}> $rows Joined rows by filter id.
     */
    private function resolver(array $rows): FilterListTableResolver
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(
            function (string $sql, array $params) use ($rows): array|false {
                $this->queries[] = [$sql, $params];

                return $rows[$params[0]] ?? false;
            },
        );

        $drivers = (new ListDriverRegistry())
            ->add(
                new class implements ListDriverInterface {
                    public function resolveDcTable(string $type, array $config, array $attributes): string
                    {
                        return 'tl_fixed';
                    }
                },
                new AsListDriver(type: 'fixed'),
                'fixed',
            )
            ->add(
                new class implements ListDriverInterface {
                    public function resolveDcTable(string $type, array $config, array $attributes): string
                    {
                        return (string) ($config['dc'] ?? '');
                    }
                },
                new AsListDriver(type: 'stored'),
                'stored',
            );

        return new FilterListTableResolver($connection, $drivers);
    }
}
