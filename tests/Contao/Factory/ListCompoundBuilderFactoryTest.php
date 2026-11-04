<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Factory;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCollector;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory as CoreFilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundBuilderFactory as CoreListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\List\Factory\ListCompoundFactory;
use HeimrichHannot\FlareBundle\List\Resolver\ListDriverResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListOptionsResolver;
use HeimrichHannot\FlareBundle\List\Resolver\ListTransformerResolver;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Fixture\ListModelFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ListCompoundBuilderFactoryTest extends TestCase
{
    public function testCreateCarriesTheModelAndItsSource(): void
    {
        // A list without id has no stored filters to collect (and needs no database).
        $model = new ListModelFixture(['id' => 0, 'type' => 'test_list', 'dc' => 'tl_test']);

        $builder = $this->createFactory()->create($model);

        self::assertSame($model, $builder->getModel());
        self::assertSame('test_list', $builder->getDriver());
        self::assertSame('tl_flare_list.0', $builder->getSource());
        self::assertSame([], $builder->getFilters());
        self::assertSame('tl_test', $builder->build()->dc);
    }

    public function testUnknownDriverThrows(): void
    {
        $this->expectException(FlareException::class);

        $this->createFactory()->create(new ListModelFixture(['id' => 3, 'type' => 'gone']));
    }

    private function createFactory(): ListCompoundBuilderFactory
    {
        $dispatcher = new EventDispatcher();

        $registry = new ListDriverRegistry();
        $registry->add(
            new class extends AbstractListDriver {},
            new AsListDriver(type: 'test_list', dataContainer: 'tl_test'),
            'test_list',
        );
        $driverResolver = new ListDriverResolver($registry);

        $filterCompoundFactory = new FilterCompoundFactory(
            filterCompoundFactory: new CoreFilterCompoundFactory(
                filterElementRegistry: new FilterElementRegistry(),
                filterControlRegistry: new FilterControlRegistry(),
                filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
                filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), $dispatcher),
            ),
            valueProviderRegistry: new ValueProviderRegistry(),
            logger: new NullLogger(),
        );

        return new ListCompoundBuilderFactory(
            builderFactory: new CoreListCompoundBuilderFactory(
                eventDispatcher: $dispatcher,
                listDriverResolver: $driverResolver,
                listCompoundFactory: new ListCompoundFactory(
                    $registry,
                    new ListOptionsResolver(new SchemaResolver()),
                    new ListTransformerResolver(new SourceTransformer(), $dispatcher),
                    $driverResolver,
                ),
            ),
            filterCollector: new FilterCollector($dispatcher, $filterCompoundFactory, $registry, new NullLogger()),
        );
    }
}
