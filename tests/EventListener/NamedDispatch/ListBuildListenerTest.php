<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Event\ListBuildEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\ListBuildListener;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListBuildListenerTest extends TestCase
{
    public function testDispatchesNamedEventForStringDriverType(): void
    {
        self::assertSame(['flare.list.a.build'], $this->dispatchedNames('a'));
    }

    public function testInstanceDriverTriggersNoNamedDispatch(): void
    {
        $driver = new class extends AbstractListDriver {};

        self::assertSame([], $this->dispatchedNames($driver));
    }

    /**
     * @return list<string>
     */
    private function dispatchedNames(ListDriverInterface|string $driver): array
    {
        $names = [];

        $dispatcher = new EventDispatcher();

        foreach (['a', 'b'] as $type)
        {
            $dispatcher->addListener(
                "flare.list.{$type}.build",
                static function () use (&$names, $type): void {
                    $names[] = "flare.list.{$type}.build";
                },
            );
        }

        $builder = new ListSpecBuilder(
            specFactory: new ListSpecFactory(
                new ListDriverRegistry(),
                new ListOptionsResolver(new SchemaResolver()),
                new ListTransformerResolver($dispatcher),
            ),
            eventDispatcher: $dispatcher,
            driver: $driver,
        );

        $listener = new ListBuildListener($dispatcher);
        $listener(new ListBuildEvent($builder));

        return $names;
    }
}
