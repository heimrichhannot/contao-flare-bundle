<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\ListDriverBuildListListener;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecFactory;
use HeimrichHannot\FlareBundle\List\ListSpecBuilder;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListDriverBuildListListenerTest extends TestCase
{
    public function testDispatchesNamedEventForStringDriverType(): void
    {
        self::assertSame(['flare.list_driver.a.build_list'], $this->dispatchedNames('a'));
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
                "flare.list_driver.{$type}.build_list",
                static function () use (&$names, $type): void {
                    $names[] = "flare.list_driver.{$type}.build_list";
                },
            );
        }

        $registry = new ListDriverRegistry();
        $listDriverResolver = new ListDriverResolver($registry);

        $builder = new ListSpecBuilder(
            listDriverResolver: $listDriverResolver,
            specFactory: new ListSpecFactory(
                $registry,
                new ListOptionsResolver(new SchemaResolver()),
                new ListTransformerResolver(new SourceTransformer(), $dispatcher),
                $listDriverResolver,
            ),
            eventDispatcher: $dispatcher,
            driver: $driver,
        );

        $listener = new ListDriverBuildListListener($dispatcher);
        $listener(new ListDriverBuildListEvent($builder));

        return $names;
    }
}
