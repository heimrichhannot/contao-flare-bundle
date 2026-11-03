<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\DependencyInjection;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use HeimrichHannot\FlareBundle\Contao\BackendModule;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareFilter\FieldsOptionsCallbacks;
use HeimrichHannot\FlareBundle\Contao\Callback\FlareList\ConfigOnSubmitCallback;
use HeimrichHannot\FlareBundle\Contao\DataContainer\BackendElementScopeFactory;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\Contao\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Contao\DataContainer\FilterContainer;
use HeimrichHannot\FlareBundle\Contao\DataContainer\ListContainer;
use HeimrichHannot\FlareBundle\Contao\Hook\BreadcrumbHook;
use HeimrichHannot\FlareBundle\Contao\Hook\ElementDcaHook;
use HeimrichHannot\FlareBundle\Contao\Hook\FormNameRegexpHook;
use HeimrichHannot\FlareBundle\Contao\Inspection\FilterIssue;
use HeimrichHannot\FlareBundle\Contao\Inspection\Inspector\FilterInspector;
use HeimrichHannot\FlareBundle\Contao\Inspection\ListInspection;
use HeimrichHannot\FlareBundle\Contao\Inspection\Reporter\FilterIssueReporter;
use HeimrichHannot\FlareBundle\Contao\PtableInference\PtableInferrable;
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
            BackendElementScopeFactory::class,
            ListContainer::class,
            FilterContainer::class,
            ElementDcaHook::class,
            BreadcrumbHook::class,
            FormNameRegexpHook::class,
            ConfigOnSubmitCallback::class,
            FieldsOptionsCallbacks::class,
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
            FilterIssue::class,
            ListInspection::class,
            PtableInferrer::class,
            PtableInferrable::class,
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
}
