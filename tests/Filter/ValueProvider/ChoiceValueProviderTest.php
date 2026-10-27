<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\ValueProvider;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Contract\FilterElement\ChoiceSourceContract;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilder;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\UndefinedConfigKeyError;
use HeimrichHannot\FlareBundle\Exception\UnreadableValueException;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceObjectInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ChoiceValueProvider;
use HeimrichHannot\FlareBundle\Form\ChoicesBuilder;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Model\ListModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Translation\IdentityTranslator;

final class ChoiceValueProviderTest extends TestCase
{
    public function testResolvesStoredKeysToReferenceChoices(): void
    {
        $value = (self::provider())->provide(\serialize(['k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testUnreadableKeysAreSkippedWhenOthersResolve(): void
    {
        $value = (self::provider())
            ->provide(\serialize(['k2', 'garbage', 'k1']), new ProviderStubChoiceElement());

        self::assertSame(['k1', 'k2'], \array_map(ChoiceKey::of(...), $value->choices));
    }

    public function testEmptyStoredValueIsNoValue(): void
    {
        $p = self::provider();
        $el = new ProviderStubChoiceElement();

        self::assertNull($p->provide('', $el));
        self::assertNull($p->provide(null, $el));
        self::assertNull($p->provide(\serialize([]), $el));
    }

    public function testUnreadableNonEmptyValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (self::provider())->provide(\serialize(['garbage']), new ProviderStubChoiceElement());
    }

    public function testNonSerializedValueFailsClosed(): void
    {
        $this->expectException(UnreadableValueException::class);

        (self::provider())->provide('k1', new ProviderStubChoiceElement());
    }

    public function testElementWithoutChoiceSourceIsRejected(): void
    {
        $this->expectException(FilterException::class);

        (self::provider())->provide(\serialize(['k1']), new ProviderStubElement());
    }

    public function testUndefinedConfigKeyEscapesTheOptionsCallback(): void
    {
        $element = new class extends ProviderStubElement implements ChoiceSourceContract {
            public function buildChoices(ChoicesBuilder $choices, ElementScope $scope): void
            {
                $scope->get('typo');
            }

            public function choiceFromKey(string $key): ?ChoiceObjectInterface
            {
                return null;
            }
        };

        $scope = new ElementScope(
            list: new ListSpec(driver: $this->createStub(ListDriverInterface::class), type: 'test', dc: 'tl_test'),
            filter: new Filter(
                element: new ElementBinding($element, 'test_element', new ComponentConfig(['field' => 'title'])),
                alias: 'test',
            ),
            engineContext: new ConfigurationContext(),
        );

        $builder = new DcaFieldBuilder();
        self::provider()->buildDcaField($builder, new DcaContext(
            'tl_flare_filter',
            'test',
            (new \ReflectionClass(ListModel::class))->newInstanceWithoutConstructor(),
            null,
            static fn (): null => null,
            $element,
            static fn (): ElementScope => $scope,
        ));

        $definition = [];
        $builder->applyTo($definition);

        $this->expectException(UndefinedConfigKeyError::class);

        $definition['options_callback']();
    }

    private static function provider(): ChoiceValueProvider
    {
        return new ChoiceValueProvider(
            new ChoicesBuilderFactory(new IdentityTranslator(), new ParameterBag()),
        );
    }
}
