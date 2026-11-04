<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\FlareBundle\Contao\BackendModule;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\FieldsOptionsCallbacks;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareList\ConfigOnSubmitCallback;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareList\FieldsOptionsCallbacks as ListFieldsOptionsCallbacks;
use HeimrichHannot\FlareBundle\Contao\DataContainer\BackendElementContextFactory;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\ControlDcaBuilder;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Contao\DcaContext;
use HeimrichHannot\FlareBundle\Contao\CollectedFilters;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCollector;
use HeimrichHannot\FlareBundle\Contao\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Contao\Factory\ListCompoundBuilderFactory;
use HeimrichHannot\FlareBundle\Contao\Hook\BreadcrumbHook;
use HeimrichHannot\FlareBundle\Contao\Hook\ElementDcaHook;
use HeimrichHannot\FlareBundle\Contao\Hook\FormNameRegexpHook;
use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\Contao\Inspection\Inspector\FilterInspector;
use HeimrichHannot\FlareBundle\Contao\Inspection\ListInspection;
use HeimrichHannot\FlareBundle\Contao\Inspection\Reporter\FilterIssueReporter;
use HeimrichHannot\FlareBundle\Contao\PtableInference\PtableInferrable;
use HeimrichHannot\FlareBundle\Engine\Intent\AggregationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\AggregationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\InteractiveIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\ValidationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\ValidationIntent;
use HeimrichHannot\FlareBundle\Contao\PtableInference\PtableInferrer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * Loads config/services.yaml and checks which classes it registers as services.
 */
final class ServicesConfigTest extends TestCase
{
    private static ?ContainerBuilder $container = null;

    private static function container(): ContainerBuilder
    {
        if (self::$container === null)
        {
            self::$container = new ContainerBuilder();
            (new YamlFileLoader(self::$container, new FileLocator(__DIR__ . '/../../config')))->load('services.yaml');
        }

        return self::$container;
    }

    /**
     * Symfony keeps excluded classes as abstract definitions tagged `container.excluded`.
     */
    private static function isService(string $class): bool
    {
        $container = self::container();

        return $container->hasDefinition($class)
            && !$container->getDefinition($class)->hasTag('container.excluded');
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideServices(): iterable
    {
        foreach ([
            FilterInspector::class,
            FilterIssueReporter::class,
            BackendElementContextFactory::class,
            ListContainer::class,
            FilterContainer::class,
            ElementDcaHook::class,
            BreadcrumbHook::class,
            FormNameRegexpHook::class,
            ConfigOnSubmitCallback::class,
            FieldsOptionsCallbacks::class,
            FilterCompoundFactory::class,
            FilterCollector::class,
            ListCompoundBuilderFactory::class,
            InteractiveIntentFactory::class,
            ValidationIntentFactory::class,
            AggregationIntentFactory::class,
        ] as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideNonServices(): iterable
    {
        foreach ([
            BackendModule::class,
            DcaBuilder::class,
            DcaContext::class,
            ControlDcaBuilder::class,
            CollectedFilters::class,
            FilterIssue::class,
            ListInspection::class,
            PtableInferrer::class,
            PtableInferrable::class,
            InteractiveIntent::class,
            ValidationIntent::class,
            AggregationIntent::class,
            ConfigurationIntent::class,
        ] as $class) {
            yield $class => [$class];
        }
    }

    /**
     * @dataProvider provideServices
     */
    public function testContaoServicesAreRegistered(string $class): void
    {
        self::assertTrue(self::isService($class), "{$class} must be a service.");
    }

    /**
     * @dataProvider provideNonServices
     */
    public function testContaoNonServicesAreNotRegistered(string $class): void
    {
        self::assertTrue(\class_exists($class) || \interface_exists($class), "{$class} must exist.");
        self::assertFalse(self::isService($class), "{$class} must not be a service.");
    }

    public function testFilterInspectorReceivesTheDebugFlag(): void
    {
        self::assertSame(
            '%kernel.debug%',
            self::container()->getDefinition(FilterInspector::class)->getArgument('$debug'),
        );
    }

    public function testHooksAndCallbacksKeepTheirAttributes(): void
    {
        $hook = (new \ReflectionClass(ElementDcaHook::class))->getAttributes(AsHook::class);

        self::assertCount(1, $hook);
        self::assertSame(['loadDataContainer', 'priority' => -100], $hook[0]->getArguments());

        $callback = (new \ReflectionMethod(ConfigOnSubmitCallback::class, '__invoke'))->getAttributes(AsCallback::class);

        self::assertCount(1, $callback);
        self::assertSame([ListContainer::TABLE_NAME, 'config.onsubmit'], $callback[0]->getArguments());
    }

    /**
     * Contao resolves a string callback (`[Foo::class, 'method']` in a DCA file) through the container only if the
     * service is public; otherwise it instantiates the class without its dependencies.
     */
    public function testStringCallbackServicesArePublic(): void
    {
        foreach (\glob(__DIR__ . '/../../contao/dca/*.php') as $file)
        {
            $code = (string) \file_get_contents($file);
            \preg_match_all('/^use ([\\\\\w]+);/m', $code, $uses);
            $imports = [];

            foreach ($uses[1] as $use) {
                $imports[\substr($use, (int) \strrpos($use, '\\') + 1)] = $use;
            }

            \preg_match_all("/_callback' => \\[(\\w+)::class, '/", $code, $callbacks);

            foreach ($callbacks[1] as $short)
            {
                $class = $imports[$short] ?? $short;

                self::assertTrue(self::isService($class), \basename($file) . ": {$class} must be a service.");
                self::assertTrue(
                    self::container()->getDefinition($class)->isPublic(),
                    \basename($file) . ": {$class} must be public.",
                );
            }
        }

        $this->addToAssertionCount(1);
    }

    public function testSortSettingsColumnOptionsComeFromAnAttributeCallback(): void
    {
        $callback = (new \ReflectionMethod(ListFieldsOptionsCallbacks::class, 'getFieldOptions_sortSettings'))
            ->getAttributes(AsCallback::class);

        self::assertCount(1, $callback);
        self::assertSame(
            [ListContainer::TABLE_NAME, 'fields.sortSettings.fields.column.options'],
            $callback[0]->getArguments(),
        );
        self::assertStringNotContainsString(
            'getFieldOptions_sortSettings',
            (string) \file_get_contents(__DIR__ . '/../../contao/dca/tl_flare_list.php'),
        );
    }
}
