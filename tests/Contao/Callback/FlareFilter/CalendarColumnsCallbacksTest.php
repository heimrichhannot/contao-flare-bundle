<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Callback\FlareFilter;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\CalendarColumnsCallbacks;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\Element\StubsContaoDcaTrait;
use PHPUnit\Framework\TestCase;

final class CalendarColumnsCallbacksTest extends TestCase
{
    use StubsContaoDcaTrait;

    private const FILTER_ID = 7;

    protected function setUp(): void
    {
        $this->stubDca('tl_test', ['fields' => [
            'startTime' => ['sql' => 'bigint(20) NULL'],
            'begins' => ['sql' => ['type' => 'integer', 'name' => 'physical']],
            'day' => ['sql' => 'date NULL'],
            'virtual' => ['inputType' => 'text'],
        ]]);
    }

    public function testOptionsArePhysicalNames(): void
    {
        self::assertSame(
            ['physical' => 'tl_test.begins', 'startTime' => 'tl_test.startTime'],
            $this->callbacks('tl_test')->getColumnOptions($this->dataContainer(self::FILTER_ID)),
        );
    }

    public function testNoTableNoOptions(): void
    {
        self::assertSame([], $this->callbacks('')->getColumnOptions($this->dataContainer(self::FILTER_ID)));
        self::assertSame([], $this->callbacks('tl_test')->getColumnOptions($this->dataContainer(99)));
    }

    public function testEveryPickerGetsTheOptions(): void
    {
        $fields = [];

        $method = new \ReflectionMethod(CalendarColumnsCallbacks::class, 'getColumnOptions');

        foreach ($method->getAttributes(AsCallback::class) as $attribute)
        {
            [$table, $target] = $attribute->getArguments();

            self::assertSame(FilterContainer::TABLE_NAME, $table);
            $fields[] = $target;
        }

        self::assertSame([
            'fields.elm_periodStartField.options',
            'fields.elm_periodStopField.options',
            'fields.elm_recurringField.options',
            'fields.elm_recurrencesField.options',
            'fields.elm_repeatEndField.options',
        ], $fields);
    }

    /**
     * @param string $table The table the list driver resolves for the filter {@see self::FILTER_ID}.
     */
    private function callbacks(string $table): CalendarColumnsCallbacks
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturnCallback(
            static fn (string $sql, array $params): array|false =>
                $params[0] === self::FILTER_ID ? ['type' => 'stored', 'dc' => $table] : false,
        );

        $drivers = (new ListDriverRegistry())->add(
            new class implements ListDriverInterface {
                public function resolveDcTable(string $type, array $config, array $attributes): string
                {
                    return (string) ($config['dc'] ?? '');
                }
            },
            new AsListDriver(type: 'stored'),
            'stored',
        );

        return new CalendarColumnsCallbacks(new FilterListTableResolver($connection, $drivers));
    }

    private function dataContainer(int $id): DataContainer
    {
        $dc = $this->createMock(DataContainer::class);
        $dc->method('__get')->willReturnCallback(static fn (string $key): mixed => $key === 'id' ? $id : null);

        return $dc;
    }
}
