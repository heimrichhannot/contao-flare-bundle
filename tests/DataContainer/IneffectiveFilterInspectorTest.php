<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DataContainer\IneffectiveFilterInspector;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\Model\FilterModel;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class IneffectiveFilterInspectorTest extends TestCase
{
    public function testDescribesAnArchiveWhitelistForOptionsOnlyWithoutAControl(): void
    {
        self::assertSame(
            'filter.info.ineffective.whitelist_for_options_only',
            $this->inspector()->describe(self::model(['elm_useWhitelistForOptionsOnly' => '1'])),
        );
    }

    public function testEffectiveFiltersAreNotDescribed(): void
    {
        $inspector = $this->inspector();

        self::assertNull($inspector->describe(self::model(['elm_useWhitelistForOptionsOnly' => ''])));
        self::assertNull($inspector->describe(self::model([
            'elm_useWhitelistForOptionsOnly' => '1',
            'control' => 'flare_choice',
        ])));
    }

    public function testFiltersThatCannotBeBuiltAreNotDescribed(): void
    {
        self::assertNull($this->inspector()->describe(self::model([
            'elm_useWhitelistForOptionsOnly' => '1',
            'value' => 'unreadable',
        ])), 'an unreadable fixed value fails the filter');
    }

    public function testRowsOfElementsWithoutTheContractAreNeverLoaded(): void
    {
        // A row of another type would need the database to be loaded; it is answered from the registry alone.
        self::assertNull($this->inspector()->describe(['id' => 7, 'type' => 'flare_other']));
        self::assertNull($this->inspector()->describe(['id' => 7, 'type' => 'flare_unknown']));
    }

    private function inspector(): IneffectiveFilterInspector
    {
        $elements = (new FilterElementRegistry())
            ->add(
                new ArchiveFilterElement(),
                new AsFilterElement(type: ArchiveFilterElement::TYPE, value: ChoiceValue::class),
                ArchiveFilterElement::TYPE,
            )
            ->add(
                $this->createMock(FilterElementInterface::class),
                new AsFilterElement(type: 'flare_other'),
                'flare_other',
            );

        $controls = (new FilterControlRegistry())
            ->add($this->createMock(FilterControlInterface::class), 'flare_choice', ChoiceValue::class);

        $providers = (new ValueProviderRegistry())->add(new class implements ValueProviderInterface {
            public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface
            {
                return $stored === 'unreadable' ? throw new FlareException('Unreadable value.') : null;
            }

            public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
        }, ChoiceValue::class);

        $factory = new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: $controls,
            valueProviderRegistry: $providers,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: new NullLogger(),
        );

        return new IneffectiveFilterInspector($elements, $factory, new class implements TranslatorInterface {
            use TranslatorTrait;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function model(array $row): FilterModel
    {
        $model = (new \ReflectionClass(FilterModel::class))->newInstanceWithoutConstructor();

        (function (array $row): void {
            $this->arrData = $row;
        })->call($model, $row + [
            'id' => 7,
            'type' => ArchiveFilterElement::TYPE,
            'control' => '',
            'value' => '',
            'elm_whitelistParents' => \serialize([1]),
        ]);

        return $model;
    }
}
