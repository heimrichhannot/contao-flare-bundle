<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Event\ListDriverTransformerEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\ListDriverTransformerListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListDriverTransformerListenerTest extends TestCase
{
    public function testEventIsDispatchedUnderTheListDriverType(): void
    {
        $names = [];
        $dispatcher = new EventDispatcher();

        foreach (['flare.list_driver.flare_news.transformers', 'flare.list.flare_news.transformers'] as $name) {
            $dispatcher->addListener($name, static function () use (&$names, $name): void {
                $names[] = $name;
            });
        }

        (new ListDriverTransformerListener($dispatcher))(new ListDriverTransformerEvent(
            new TransformerResolver(),
            $this->createMock(ListDriverInterface::class),
            'flare_news',
        ));

        self::assertSame(['flare.list_driver.flare_news.transformers'], $names);
    }
}
