<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Event\FilterControlDcaEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementDcaEvent;
use HeimrichHannot\FlareBundle\Event\ListDcaEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\DcaEventListener;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class DcaEventListenerTest extends TestCase
{
    public function testListEventIsDispatchedUnderTheListType(): void
    {
        $listener = new DcaEventListener($this->recordingDispatcher($names));

        $listener->onListDca(new ListDcaEvent(new DcaBuilder(), self::context('tl_flare_list', 'flare_news')));

        self::assertSame(['flare.list.flare_news.dca'], $names);
    }

    public function testFilterElementEventIsDispatchedUnderTheElementType(): void
    {
        $listener = new DcaEventListener($this->recordingDispatcher($names));

        $listener->onFilterElementDca(
            new FilterElementDcaEvent(new DcaBuilder(), self::context('tl_flare_filter', 'flare_archive')),
        );

        self::assertSame(['flare.filter_element.flare_archive.dca'], $names);
    }

    public function testFilterControlEventIsDispatchedUnderTheControlType(): void
    {
        $listener = new DcaEventListener($this->recordingDispatcher($names));

        $listener->onFilterControlDca(new FilterControlDcaEvent(
            (new DcaBuilder())->scope('control'),
            self::context('tl_flare_filter', 'flare_archive'),
            'flare_choice',
        ));

        self::assertSame(['flare.filter_control.flare_choice.dca'], $names);
    }

    /**
     * @param list<string>|null $names Receives the names of the dispatched named events.
     */
    private function recordingDispatcher(?array &$names): EventDispatcher
    {
        $names = [];
        $dispatcher = new EventDispatcher();

        foreach ([
            'flare.list.flare_news.dca',
            'flare.filter_element.flare_archive.dca',
            'flare.filter_control.flare_choice.dca',
            'flare.filter_control.flare_archive.dca',
        ] as $name) {
            $dispatcher->addListener($name, static function () use (&$names, $name): void {
                $names[] = $name;
            });
        }

        return $dispatcher;
    }

    private static function context(string $table, string $type): DcaContext
    {
        return new DcaContext(
            $table,
            $type,
            (new \ReflectionClass(ListModel::class))->newInstanceWithoutConstructor(),
            null,
            static fn (): null => null,
        );
    }
}
