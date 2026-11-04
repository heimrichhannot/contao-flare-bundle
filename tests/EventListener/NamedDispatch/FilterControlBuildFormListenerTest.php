<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FilterControlBuildFormListener;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilder;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListCompound;
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

        $intent = new class implements IntentInterface {
            public static function getIntentType(): string
            {
                return 'test';
            }
        };

        $list = new ListCompound(driver: $driver, type: 'test_list', dc: 'tl_test');
        $filter = new FilterCompound(
            element: new ElementBinding(new StubFilterElement(), 'b', new ComponentConfig(), null),
            alias: 'suche',
            control: new ControlBinding(new KeywordsFilterControl(), $controlType, new ComponentConfig()),
        );

        $context = new ControlContext(list: $list, filter: $filter, intent: $intent);

        $builder = new FilterFormBuilder('suche', null, new EventDispatcher(), Forms::createFormFactory());

        $listener = new FilterControlBuildFormListener($dispatcher);
        $listener->onFilterControlBuildForm(new FilterControlBuildFormEvent($builder, $context));

        return $names;
    }
}
