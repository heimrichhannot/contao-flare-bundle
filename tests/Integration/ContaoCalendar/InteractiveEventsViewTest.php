<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Integration\ContaoCalendar;

use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\ListEntry\EntryCollector;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Integration\ContaoCalendar\View\InteractiveEventsView;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\Entry;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\Paginator;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormInterface;

final class InteractiveEventsViewTest extends TestCase
{
    public function testGroupsEntriesWithRepeatedIdsByEventGroup(): void
    {
        $loader = $this->createMock(InteractiveLoaderInterface::class);
        $loader->method('fetchEntries')->willReturn([
            ['id' => 7, '_flare_event_group' => '2026-11-11'],
            ['id' => 8, '_flare_event_group' => '2026-11-11'],
            ['id' => 7, '_flare_event_group' => '2026-11-12'],
        ]);

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_calendar_events';
            }
        };
        $list = new ListCompound(driver: $driver, type: 'flare_events', dc: 'tl_calendar_events');
        $intent = new InteractiveIntent(new PaginatorConfig(itemsPerPage: 10), formName: 'f');

        $view = new InteractiveEventsView(
            loader: $loader,
            form: $this->createMock(FormInterface::class),
            paginator: new Paginator(10, 1, 3, static fn (int $page): string => ''),
            collector: new EntryCollector(new EventDispatcher(), $list, $intent),
            lookup: $this->createMock(EntryLookupInterface::class),
            totalItems: 3,
        );

        $grouped = $view->getEntriesGrouped();
        self::assertSame(['2026-11-11', '2026-11-12'], \array_keys($grouped));
        self::assertSame([7, 8], \array_map(static fn (Entry $e): int => $e->row['id'], $grouped['2026-11-11']));
        self::assertContainsOnlyInstancesOf(Entry::class, $grouped['2026-11-12']);
        self::assertSame($grouped, $view->getEntriesGrouped());
    }
}
