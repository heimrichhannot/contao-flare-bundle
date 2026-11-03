<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DataContainer;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\DataContainer\BackendElementScopeFactory;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaFieldBuilderInterface;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Filter\ValueProvider\ValueProviderInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Fixture\FilterModelFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class BackendElementScopeFactoryTest extends TestCase
{
    public function testTheBackendScopeKnowsTheControlButReadsNoValue(): void
    {
        $scope = $this->createFactory()->create($this->getSpec(), self::archiveFilter());

        self::assertNotNull($scope);
        self::assertSame('flare_choice', $scope->filter->control?->type, 'the control decides the options');
        self::assertNull($scope->filter->value);
    }

    public function testAScopeThatCannotBeBuiltIsNull(): void
    {
        $getSpec = static fn (): ListSpec => throw new FlareException('No list driver found for type "gone"');

        self::assertNull($this->createFactory()->create($getSpec, self::archiveFilter()));
    }

    /**
     * @return \Closure(): ListSpec
     */
    private function getSpec(): \Closure
    {
        $driver = $this->createStub(ListDriverInterface::class);

        return static fn (): ListSpec => new ListSpec(driver: $driver, type: 'test', dc: 'tl_news');
    }

    private static function archiveFilter(): FilterModelFixture
    {
        return new FilterModelFixture([
            'id' => 7,
            'type' => ArchiveFilterElement::TYPE,
            'control' => 'flare_choice',
            'value' => 'tl_news_archive.5',
        ]);
    }

    private function createFactory(): BackendElementScopeFactory
    {
        $elements = (new FilterElementRegistry())->add(
            new ArchiveFilterElement(),
            new AsFilterElement(type: ArchiveFilterElement::TYPE, value: ChoiceValue::class),
            ArchiveFilterElement::TYPE,
        );

        $controls = (new FilterControlRegistry())
            ->add($this->createMock(FilterControlInterface::class), 'flare_choice', ChoiceValue::class);

        $providers = (new ValueProviderRegistry())->add(new class implements ValueProviderInterface {
            public function provide(mixed $stored, FilterElementInterface $element): ?ValueInterface
            {
                // Readable: the backend scope must not read it even then.
                return new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)]);
            }

            public function buildDcaField(DcaFieldBuilderInterface $field, DcaContext $context): void {}
        }, ChoiceValue::class);

        return new BackendElementScopeFactory(new FilterFactory(
            filterElementRegistry: $elements,
            filterControlRegistry: $controls,
            valueProviderRegistry: $providers,
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), new EventDispatcher()),
            logger: new NullLogger(),
        ));
    }
}
