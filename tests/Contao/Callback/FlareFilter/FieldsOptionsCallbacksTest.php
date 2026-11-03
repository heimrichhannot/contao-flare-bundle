<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Callback\FlareFilter;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterControl;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\FieldsOptionsCallbacks;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\List\Factory\ListSpecBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\Loader\PhpFileLoader;
use Symfony\Component\Translation\Translator;

final class FieldsOptionsCallbacksTest extends TestCase
{
    private const TRANSLATIONS = __DIR__ . '/../../../../translations';

    public function testControlOptionsAreLabelledFromTheControlDomain(): void
    {
        $controls = (new FilterControlRegistry())
            ->add($this->createMock(FilterControlInterface::class), 'flare_choice_bool', BoolValue::class)
            ->add($this->createMock(FilterControlInterface::class), 'flare_choice', ChoiceValue::class)
            ->add($this->createMock(FilterControlInterface::class), 'unlabelled', BoolValue::class);

        $elements = (new FilterElementRegistry())->add(
            $this->createMock(FilterElementInterface::class),
            new AsFilterElement(type: 'bool', value: BoolValue::class),
            'bool',
        );

        $translator = new Translator('de');
        $translator->addLoader('php', new PhpFileLoader());
        $translator->addResource(
            'php',
            self::TRANSLATIONS . '/flare_filter_control.de.php',
            'de',
            'flare_filter_control',
        );

        $callbacks = new FieldsOptionsCallbacks(
            contaoFramework: $this->createMock(ContaoFramework::class),
            filterContainer: $this->createMock(FilterContainer::class),
            filterControlRegistry: $controls,
            filterElementRegistry: $elements,
            translator: $translator,
            // Neither list collaborator is reached by the control options.
            listFactory: (new \ReflectionClass(ListSpecBuilderFactory::class))->newInstanceWithoutConstructor(),
            listExecutionContextFactory: new ListExecutionContextFactory(new EventDispatcher()),
        );

        $dc = $this->createMock(DataContainer::class);
        $dc->method('getCurrentRecord')->willReturn(['type' => 'bool']);

        self::assertSame(
            ['flare_choice_bool' => 'Ja/Nein-Auswahl', 'unlabelled' => 'unlabelled'],
            $callbacks->getFieldOptions_control($dc),
        );
    }

    /**
     * @dataProvider provideLocales
     */
    public function testEveryBundledControlHasALabel(string $locale): void
    {
        $labels = require self::TRANSLATIONS . "/flare_filter_control.$locale.php";

        foreach (\glob(__DIR__ . '/../../../../src/Filter/Control/*FilterControl.php') as $file)
        {
            $class = 'HeimrichHannot\\FlareBundle\\Filter\\Control\\' . \basename($file, '.php');
            $reflection = new \ReflectionClass($class);

            foreach ($reflection->getAttributes(AsFilterControl::class) as $attribute) {
                self::assertArrayHasKey($attribute->newInstance()->type, $labels, $class);
            }
        }
    }

    public static function provideLocales(): iterable
    {
        yield 'de' => ['de'];
        yield 'en' => ['en'];
    }
}
