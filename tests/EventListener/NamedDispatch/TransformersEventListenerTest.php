<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Event\FilterControlConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Event\FilterElementConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\Event\ListDriverConfigureTransformersEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\TransformersEventListener;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class TransformersEventListenerTest extends TestCase
{
    private const NAMES = [
        'flare.filter_element.x.transformers',
        'flare.filter_control.x.transformers',
        'flare.list_driver.x.transformers',
    ];

    public function testElementEventIsDispatchedUnderTheElementPrefix(): void
    {
        $names = [];
        $listener = new TransformersEventListener($this->recordingDispatcher($names));

        $listener->onFilterElementConfigureTransformers(new FilterElementConfigureTransformersEvent(
            new TransformerMap(),
            $this->createMock(FilterElementInterface::class),
            'x',
        ));

        self::assertSame(['flare.filter_element.x.transformers'], $names);
    }

    public function testControlEventIsDispatchedUnderTheControlPrefix(): void
    {
        $names = [];
        $listener = new TransformersEventListener($this->recordingDispatcher($names));

        $listener->onFilterControlConfigureTransformers(new FilterControlConfigureTransformersEvent(
            new TransformerMap(),
            $this->createMock(FilterControlInterface::class),
            'x',
        ));

        self::assertSame(['flare.filter_control.x.transformers'], $names);
    }

    public function testListDriverEventIsDispatchedUnderTheListDriverPrefix(): void
    {
        $names = [];
        $listener = new TransformersEventListener($this->recordingDispatcher($names));

        $listener->onListDriverConfigureTransformers(new ListDriverConfigureTransformersEvent(
            new TransformerMap(),
            $this->createMock(ListDriverInterface::class),
            'x',
        ));

        self::assertSame(['flare.list_driver.x.transformers'], $names);
    }

    /**
     * @param list<string> $names Receives the names of the dispatched named events.
     */
    private function recordingDispatcher(array &$names): EventDispatcher
    {
        $dispatcher = new EventDispatcher();

        foreach (self::NAMES as $name) {
            $dispatcher->addListener($name, static function () use (&$names, $name): void {
                $names[] = $name;
            });
        }

        return $dispatcher;
    }
}
