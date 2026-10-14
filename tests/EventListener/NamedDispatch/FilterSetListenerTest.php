<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\NamedDispatch;

use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use HeimrichHannot\FlareBundle\EventListener\NamedDispatch\FormHarnessListener;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;

final class FilterSetListenerTest extends TestCase
{
    public function testDispatchesNamedEventForTheFormName(): void
    {
        self::assertSame(['flare.form.flare_a.build'], $this->dispatchedNames('flare_a'));
    }

    public function testNamedEventIsScopedToTheFormName(): void
    {
        self::assertSame([], $this->dispatchedNames('flare_other'));
    }

    /**
     * @return list<string>
     */
    private function dispatchedNames(string $formName): array
    {
        $names = [];

        $dispatcher = new EventDispatcher();

        foreach (['flare_a', 'flare_b'] as $name)
        {
            $dispatcher->addListener(
                "flare.form.{$name}.build",
                static function () use (&$names, $name): void {
                    $names[] = "flare.form.{$name}.build";
                },
            );
        }

        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $formBuilder = Forms::createFormFactory()->createNamedBuilder($formName, FormType::class);

        $listener = new FormHarnessListener($dispatcher);
        $listener->onFormHarnessBuildEvent(new FormHarnessBuildEvent(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test'),
            formName: $formName,
            formBuilder: $formBuilder,
        ));

        return $names;
    }
}
