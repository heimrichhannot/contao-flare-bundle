<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Event\ListDriverEntriesEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\ListDriverEntriesListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use HeimrichHannot\FlareBundle\List\Entry\EntryLookupInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListDriverEntriesListenerTest extends TestCase
{
    public function testDispatchesUnderTheDriverType(): void
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };
        $event = new ListDriverEntriesEvent(
            new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test'),
            new ValidationIntent(),
            new EntryCollection(),
            $this->createMock(EntryLookupInterface::class),
        );

        $received = [];
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            'flare.list_driver.test_list.entries',
            static function (ListDriverEntriesEvent $e) use (&$received): void {
                $received[] = $e;
            },
        );

        (new ListDriverEntriesListener($dispatcher))($event);

        self::assertSame([$event], $received);
    }
}
