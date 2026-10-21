<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterControlBuiltEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FilterControlListener;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Forms;

final class FilterControlListenerTest extends TestCase
{
    public function testDispatchesNamedEventForTheFilterElementType(): void
    {
        self::assertSame(['flare.filter_control.a.built'], $this->dispatchedNames('a'));
    }

    public function testUntypedFilterTriggersNoNamedDispatch(): void
    {
        self::assertSame([], $this->dispatchedNames(''));
    }

    /**
     * @return list<string>
     */
    private function dispatchedNames(string $type): array
    {
        $names = [];

        $dispatcher = new EventDispatcher();

        foreach (['a', 'b'] as $candidate)
        {
            $dispatcher->addListener(
                "flare.filter_control.{$candidate}.built",
                static function () use (&$names, $candidate): void {
                    $names[] = "flare.filter_control.{$candidate}.built";
                },
            );
        }

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $engineContext = new class implements ContextInterface {
            public static function getContextType(): string
            {
                return 'test';
            }
        };

        $list = new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test');
        $filter = new Filter(element: new StubFilterElement(), type: $type, alias: 'suche');

        $scope = new FilterScope(list: $list, filter: $filter, engineContext: $engineContext);

        $builder = new FilterFormBuilder('suche', null, new EventDispatcher(), Forms::createFormFactory());

        $listener = new FilterControlListener($dispatcher);
        $listener->onFilterControlBuiltEvent(new FilterControlBuiltEvent($builder, $scope));

        return $names;
    }
}
