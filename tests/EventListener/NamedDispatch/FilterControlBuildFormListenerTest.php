<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FilterControlBuildFormListener;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilder;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Forms;

final class FilterControlBuildFormListenerTest extends TestCase
{
    public function testDispatchesNamedEventForTheControlType(): void
    {
        self::assertSame(['flare.filter_control.a.build_form'], $this->dispatchedNames('a'));
    }

    public function testEmptyControlTypeTriggersNoNamedDispatch(): void
    {
        self::assertSame([], $this->dispatchedNames(''));
    }

    /**
     * @return list<string>
     */
    private function dispatchedNames(string $controlType): array
    {
        $names = [];

        $dispatcher = new EventDispatcher();

        foreach (['a', 'b'] as $candidate)
        {
            $dispatcher->addListener(
                "flare.filter_control.{$candidate}.build_form",
                static function () use (&$names, $candidate): void {
                    $names[] = "flare.filter_control.{$candidate}.build_form";
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
        $filter = new Filter(
            element: new ElementBinding(new StubFilterElement(), 'b', new ComponentConfig()),
            alias: 'suche',
            control: new ControlBinding(new KeywordsFilterControl(), $controlType, new ComponentConfig()),
        );

        $scope = new ControlScope(list: $list, filter: $filter, engineContext: $engineContext);

        $builder = new FilterFormBuilder('suche', null, new EventDispatcher(), Forms::createFormFactory());

        $listener = new FilterControlBuildFormListener($dispatcher);
        $listener->onFilterControlBuildForm(new FilterControlBuildFormEvent($builder, $scope));

        return $names;
    }
}
