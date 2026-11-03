<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\ConfigProvider;
use HeimrichHannot\FlareBundle\Engine\Context\ConfigurationContext;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FormulaBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\SearchKeywordsPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds real {@see FilterContext}s from an element over the real factories and every shipped predicate.
 */
trait BuildsFilterContextsTrait
{
    /**
     * @param array<string, mixed> $config
     */
    protected function buildFilterContext(
        FilterElementInterface $element,
        array                  $config = [],
        ?ValueInterface        $value = null,
        ?ContextInterface      $engineContext = null,
        string                 $dc = 'tl_test',
    ): FilterContext {
        return $this->createFilterContextFactory()->create(
            $this->buildElementScope($element, $config, $engineContext, $dc),
            $value,
        );
    }

    /**
     * @param array<string, mixed> $config Resolved through the element's schema, if it has one.
     */
    protected function buildElementScope(
        FilterElementInterface $element,
        array                  $config = [],
        ?ContextInterface      $engineContext = null,
        string                 $dc = 'tl_test',
    ): ElementScope {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ElementScope(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: $dc),
            filter: new Filter(
                element: new ElementBinding(
                    component: $element,
                    type: 'test_element',
                    config: (new FilterOptionsResolver(new SchemaResolver()))->resolve($element, $config),
                ),
                alias: 'test',
            ),
            engineContext: $engineContext ?? new ConfigurationContext(),
        );
    }

    protected function createFilterContextFactory(?FilterElementRegistry $elementRegistry = null): FilterContextFactory
    {
        return new FilterContextFactory(new FilterContextBuilderFactory(
            new FormulaBuilderFactory($this->createFilterPredicateRegistry()),
            $elementRegistry ?? new FilterElementRegistry(),
        ));
    }

    protected function createFilterPredicateRegistry(): FilterPredicateRegistry
    {
        $predicates = [];

        foreach (\glob(\dirname(__DIR__, 2) . '/src/Filter/Predicate/*Predicate.php') ?: [] as $file) {
            $class = 'HeimrichHannot\\FlareBundle\\Filter\\Predicate\\' . \basename($file, '.php');
            $reflection = new \ReflectionClass($class);

            if ($reflection->isAbstract() || !$reflection->implementsInterface(PredicateInterface::class)) {
                continue;
            }

            $predicates[] = $class === SearchKeywordsPredicate::class
                ? new SearchKeywordsPredicate($this->createConfigProvider())
                : new $class();
        }

        return new FilterPredicateRegistry($predicates);
    }

    private function createConfigProvider(): ConfigProvider
    {
        $translator = new class implements TranslatorInterface {
            public function trans(
                string  $id,
                array   $parameters = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                return $id;
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        return new ConfigProvider(new ParameterBag(), $translator);
    }
}
