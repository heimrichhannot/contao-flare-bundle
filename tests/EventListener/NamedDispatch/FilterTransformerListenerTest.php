<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\Event\FilterTransformerEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FilterTransformerListener;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\FilterComponentInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class FilterTransformerListenerTest extends TestCase
{
    public function testElementEventIsDispatchedUnderTheElementPrefix(): void
    {
        self::assertSame(
            ['flare.filter_element.x.transformers'],
            $this->dispatch($this->createMock(FilterElementInterface::class)),
        );
    }

    public function testControlEventIsDispatchedUnderTheControlPrefix(): void
    {
        self::assertSame(
            ['flare.filter_control.x.transformers'],
            $this->dispatch($this->createMock(FilterControlInterface::class)),
        );
    }

    /**
     * @return list<string>
     */
    private function dispatch(FilterComponentInterface $owner): array
    {
        $names = [];
        $dispatcher = new EventDispatcher();

        foreach (['flare.filter_element.x.transformers', 'flare.filter_control.x.transformers'] as $name) {
            $dispatcher->addListener($name, static function () use (&$names, $name): void {
                $names[] = $name;
            });
        }

        (new FilterTransformerListener($dispatcher))(new FilterTransformerEvent(new TransformerMap(), $owner, 'x'));

        return $names;
    }
}
