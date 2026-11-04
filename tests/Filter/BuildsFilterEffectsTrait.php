<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\ConfigProvider;
use HeimrichHannot\FlareBundle\Engine\Intent\ConfigurationIntent;
use HeimrichHannot\FlareBundle\Engine\Intent\IntentInterface;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterEffectBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterEffectFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FormulaBuilderFactory;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\FilterEffect;
use HeimrichHannot\FlareBundle\Filter\Predicate\PredicateInterface;
use HeimrichHannot\FlareBundle\Filter\Predicate\SearchKeywordsPredicate;
use HeimrichHannot\FlareBundle\DependencyInjection\Attribute\AsFilterElement;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterPredicateRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds real {@see FilterEffect}s from an element over the real factories and every shipped predicate.
 */
trait BuildsFilterEffectsTrait
{
    /**
     * @param array<string, mixed> $config
     */
    protected function buildFilterEffect(
        FilterElementInterface $element,
        array                  $config = [],
        ?ValueInterface        $value = null,
        ?IntentInterface       $intent = null,
        string                 $dc = 'tl_test',
    ): FilterEffect {
        return $this->createFilterEffectFactory()->create(
            $this->buildElementContext($element, $config, $intent, $dc),
            $value,
        );
    }

    /**
     * @param array<string, mixed> $config Resolved through the element's schema, if it has one.
     */
    protected function buildElementContext(
        FilterElementInterface $element,
        array                  $config = [],
        ?IntentInterface       $intent = null,
        string                 $dc = 'tl_test',
    ): ElementContext {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ElementContext(
            list: new ListSpec(driver: $driver, type: 'test_list', dc: $dc),
            filter: new Filter(
                element: new ElementBinding(
                    component: $element,
                    type: 'test_element',
                    config: (new FilterOptionsResolver(new SchemaResolver()))->resolve($element, $config),
                    valueClass: self::valueClassOf($element),
                ),
                alias: 'test',
            ),
            intent: $intent ?? new ConfigurationIntent(),
        );
    }

    /**
     * The value class from the element's {@see AsFilterElement} attribute, as the factory reads it.
     *
     * @return class-string<ValueInterface>|null
     */
    private static function valueClassOf(FilterElementInterface $element): ?string
    {
        $attributes = (new \ReflectionClass($element))->getAttributes(AsFilterElement::class);

        return isset($attributes[0]) ? $attributes[0]->newInstance()->value : null;
    }

    protected function createFilterEffectFactory(?FilterElementRegistry $elementRegistry = null): FilterEffectFactory
    {
        return new FilterEffectFactory(new FilterEffectBuilderFactory(
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
