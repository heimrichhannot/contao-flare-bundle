<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\DataContainer;

use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterListTableResolver;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsListDriver;
use HeimrichHannot\FlareBundle\Event\ListDriverBuildListEvent;
use HeimrichHannot\FlareBundle\List\Driver\AbstractListDriver;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Registry\ListDriverRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FilterListTableResolverTest extends ContaoTestCase
{
    use BuildsFilterListTableResolverTrait;

    public function testTheTableIsTheBuiltListsTable(): void
    {
        // The driver's table comes from an option its transformer reads from the list model, not from `dc`.
        $resolver = $this->resolver(
            [7 => ['pid' => 5]],
            [5 => ['type' => 'derived', 'dc' => 'tl_stored', 'sourceTable' => 'tl_derived']],
        );

        self::assertSame('tl_derived', $resolver->resolve(7));
    }

    public function testAFixedTableDriverWinsOverAnEmptyDc(): void
    {
        $resolver = $this->resolver([7 => ['pid' => 5]], [5 => ['type' => 'fixed', 'dc' => '']]);

        self::assertSame('tl_fixed', $resolver->resolve(7));
    }

    public function testTheStoredDcIsUsed(): void
    {
        $resolver = $this->resolver([7 => ['pid' => 5]], [5 => ['type' => 'stored', 'dc' => 'tl_stored']]);

        self::assertSame('tl_stored', $resolver->resolve(7));
    }

    public function testNoFilterRowOrNoListIsNull(): void
    {
        $resolver = $this->resolver([7 => ['pid' => 4]], [5 => ['type' => 'stored', 'dc' => 'tl_stored']]);

        // No filter row.
        self::assertNull($resolver->resolve(6));
        // A filter whose list is gone.
        self::assertNull($resolver->resolve(7));
    }

    public function testAListThatCannotBeBuiltIsNull(): void
    {
        $resolver = $this->resolver(
            [7 => ['pid' => 4], 8 => ['pid' => 5]],
            [4 => ['type' => 'unknown', 'dc' => 'tl_stored'], 5 => ['type' => 'stored', 'dc' => '']],
        );

        // No driver registered under the list's type.
        self::assertNull($resolver->resolve(7));
        // The driver resolves no table.
        self::assertNull($resolver->resolve(8));
    }

    public function testAListWithFilterFailuresKeepsItsTable(): void
    {
        // What keeps a broken time-window filter's pickers usable: a failed filter fails the list's output, not its
        // build.
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ListDriverBuildListEvent::class,
            static function (ListDriverBuildListEvent $event): void {
                $event->builder->addFailure(new FilterFailure('tl_flare_filter.7', 'broken'));
            },
        );

        $resolver = $this->resolver([7 => ['pid' => 5]], [5 => ['type' => 'stored', 'dc' => 'tl_stored']], $dispatcher);

        self::assertSame('tl_stored', $resolver->resolve(7));
    }

    public function testEachListIsBuiltOncePerRequest(): void
    {
        $builds = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(ListDriverBuildListEvent::class, static function () use (&$builds): void {
            ++$builds;
        });

        $resolver = $this->resolver(
            [7 => ['pid' => 5], 8 => ['pid' => 5], 9 => ['pid' => 6], 10 => ['pid' => 6]],
            [5 => ['type' => 'stored', 'dc' => 'tl_stored'], 6 => ['type' => 'stored', 'dc' => '']],
            $dispatcher,
        );

        self::assertSame('tl_stored', $resolver->resolve(7));
        self::assertSame('tl_stored', $resolver->resolve(8));
        self::assertSame(1, $builds, 'Two filters of one list build it once.');

        self::assertNull($resolver->resolve(9));
        self::assertNull($resolver->resolve(10));
        self::assertSame(2, $builds, 'A list that cannot be built is not built again.');

        $resolver->reset();

        self::assertSame('tl_stored', $resolver->resolve(7));
        self::assertSame(3, $builds, 'After a reset, the list is built again.');
    }

    /**
     * @param array<int, array<string, mixed>> $filters Filter rows by id.
     * @param array<int, array<string, mixed>> $lists   List rows by id.
     */
    private function resolver(
        array            $filters,
        array            $lists,
        ?EventDispatcher $dispatcher = null,
    ): FilterListTableResolver {
        $drivers = (new ListDriverRegistry())
            ->add(
                new class extends AbstractListDriver {
                    public function resolveDcTable(string $type, array $config, array $attributes): string
                    {
                        return 'tl_fixed';
                    }
                },
                new AsListDriver(type: 'fixed'),
                'fixed',
            )
            ->add(new class extends AbstractListDriver {}, new AsListDriver(type: 'stored'), 'stored')
            ->add(
                new class extends AbstractListDriver {
                    public function resolveDcTable(string $type, array $config, array $attributes): string
                    {
                        return $config['source_table'];
                    }

                    public function configureOptions(OptionsResolver $resolver): void
                    {
                        $resolver->define('source_table')->default('')->allowedTypes('string');
                    }

                    protected function transformListModel(ConfigBuilder $config, ListModel $model): void
                    {
                        $config->set('source_table', (string) $model->sourceTable);
                    }
                },
                new AsListDriver(type: 'derived'),
                'derived',
            );

        return $this->createFilterListTableResolver($filters, $lists, $drivers, $dispatcher);
    }
}
