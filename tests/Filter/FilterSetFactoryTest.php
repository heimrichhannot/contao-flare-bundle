<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterFormBuiltEvent;
use HeimrichHannot\FlareBundle\Event\FilterSetBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterContextFactory;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterSetFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\LogicSequencerInterface;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterMount;
use HeimrichHannot\FlareBundle\Filter\FilterSet;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

final class FilterSetFactoryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    private function createFactory(): FilterSetFactory
    {
        // The CSRF extension only needs to define the "csrf_protection" option; the factory
        // always disables it, so the token manager is never used.
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->getFormFactory();

        return new FilterSetFactory(
            eventDispatcher: $this->eventDispatcher,
            filterContextFactory: new FilterContextFactory(new FilterOptionsResolver(new SchemaResolver())),
            formFactory: $formFactory,
        );
    }

    private function createForm(array $filters): FormInterface
    {
        return $this->createFilterSet($filters)->getForm();
    }

    private function createFilterSet(array $filters): FilterSet
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return (string) ($config['dc'] ?? '');
            }
        };

        $list = new ListSpec(
            driver: $driver,
            type: 'test_list',
            dc: 'tl_test',
            filters: $filters,
        );

        $context = new class implements ContextInterface, FormContextInterface {
            public static function getContextType(): string
            {
                return 'test';
            }

            public function getFormName(): string
            {
                return 'flare_test';
            }

            public function createFormActionUrl(): ?string
            {
                return null;
            }
        };

        return $this->createFactory()->create($list, $context);
    }

    /**
     * Creates an element building its form via the given callable.
     *
     * @param callable(FilterFormBuilderInterface, FilterContext): void $buildForm
     */
    private function element(callable $buildForm): FilterElementInterface
    {
        return new class($buildForm) implements FilterElementInterface {
            /** @var callable */
            private $buildForm;

            public function __construct(callable $buildForm)
            {
                $this->buildForm = $buildForm;
            }

            public function buildForm(FilterFormBuilderInterface $builder, FilterContext $context): void
            {
                ($this->buildForm)($builder, $context);
            }

            public function buildLogic(
                LogicSequencerInterface $builder,
                FilterContext           $context,
                FilterData              $data,
            ): void {}
        };
    }

    public function testSingleFieldMountsFlatUnderTheAlias(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->setAttribute('custom.attr', 'kept');
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $form = $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);

        $this->assertTrue($form->has('suche'));

        $config = $form->get('suche')->getConfig();

        $this->assertInstanceOf(TextType::class, $config->getType()->getInnerType());
        $this->assertTrue($config->getAttribute(FilterContext::ATTR_SINGLE_FIELD));
        $this->assertSame('kept', $config->getAttribute('custom.attr'));
        $this->assertInstanceOf(FilterContext::class, $config->getAttribute(FilterContext::ATTR_SELF));
        $this->assertTrue(
            $config->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted builder',
        );
    }

    public function testSingleWithCompanionFieldIsRejected(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->add('extra', TextType::class, ['required' => false]);
        });

        $this->expectException(FlareException::class);
        $this->expectExceptionMessage(
            'Filter element cannot declare a single field and add children at the same time.',
        );

        $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);
    }

    public function testMultiFieldElementMountsNestedCompound(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->add('to', TextType::class);
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $form = $this->createForm(['range' => new Filter(element: $element, type: 'test_element', alias: 'range')]);

        $child = $form->get('range');

        $this->assertInstanceOf(FormType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertTrue($child->has('from'));
        $this->assertTrue($child->has('to'));
        $this->assertTrue(
            $child->getConfig()->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted compound',
        );
    }

    public function testElementWithoutFieldsIsNotMounted(): void
    {
        $element = $this->element(static function (): void {});

        $form = $this->createForm(['empty' => new Filter(element: $element, type: 'test_element', alias: 'empty')]);

        $this->assertFalse($form->has('empty'));
    }

    public function testInvalidAliasIsSkipped(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $form = $this->createForm(['x' => new Filter(element: $element, type: 'test_element', alias: '_.tl_flare_filter.1')]);

        $this->assertSame(0, \count($form));
    }

    public function testCancelledEventPreventsMounting(): void
    {
        $this->eventDispatcher->addListener(
            FilterFormBuiltEvent::class,
            static fn (FilterFormBuiltEvent $event) => $event->cancel(),
        );

        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $form = $this->createForm(['suche' => new Filter(element: $element, type: 'test_element', alias: 'suche')]);

        $this->assertFalse($form->has('suche'));
    }

    public function testGetFormReturnsTheRootForm(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $filterSet = $this->createFilterSet([
            'suche' => new Filter(element: $element, type: 'test_element', alias: 'suche'),
        ]);

        $form = $filterSet->getForm();

        $this->assertSame('flare_test', $form->getName());
        $this->assertTrue($form->has('suche'));
        $this->assertSame($form, $filterSet->getForm(), 'The root form is not rebuilt per call');
    }

    public function testMountMapRecordsSingleAndCompoundFiltersUnderTheirListSpecKey(): void
    {
        $single = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $compound = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->add('to', TextType::class);
        });

        $singleFilter = new Filter(element: $single, type: 'test_element', alias: 'suche');
        $compoundFilter = new Filter(element: $compound, type: 'test_element', alias: 'range');

        $filterSet = $this->createFilterSet(['k_single' => $singleFilter, 'k_compound' => $compoundFilter]);

        $this->assertSame(['k_single', 'k_compound'], \array_keys($filterSet->getMounts()));

        $singleMount = $filterSet->getFilterMount('k_single');
        $this->assertInstanceOf(FilterMount::class, $singleMount);
        $this->assertSame($singleFilter, $singleMount->filter);
        $this->assertSame('suche', $singleMount->alias);
        $this->assertSame($singleFilter, $singleMount->context->filter);
        $this->assertSame('k_single', $singleMount->context->key);

        $compoundMount = $filterSet->getFilterMount('k_compound');
        $this->assertInstanceOf(FilterMount::class, $compoundMount);
        $this->assertSame('range', $compoundMount->alias);
        $this->assertSame('k_compound', $compoundMount->context->key);
    }

    public function testGetMountResolvesTheSameChildAsTheRootForm(): void
    {
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $filterSet = $this->createFilterSet([
            'k' => new Filter(element: $element, type: 'test_element', alias: 'suche'),
        ]);

        $this->assertSame($filterSet->getForm()->get('suche'), $filterSet->getMount('k'));
    }

    public function testGetMountToleratesALeadingDigitAlias(): void
    {
        // Str::isValidFormName() permits a leading digit, which is the one alias shape where PHP
        // array-key coercion could make the root form and the mount map disagree.
        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $filterSet = $this->createFilterSet([
            'k' => new Filter(element: $element, type: 'test_element', alias: '0'),
        ]);

        $this->assertSame('0', $filterSet->getFilterMount('k')?->alias);
        $this->assertSame($filterSet->getForm()->get('0'), $filterSet->getMount('k'));
    }

    /**
     * @dataProvider provideUnmountedFilters
     */
    public function testUnmountedFiltersAreAbsentFromTheMountMap(string $alias, bool $cancel, bool $addField): void
    {
        if ($cancel) {
            $this->eventDispatcher->addListener(
                FilterFormBuiltEvent::class,
                static fn (FilterFormBuiltEvent $event) => $event->cancel(),
            );
        }

        $element = $this->element(static function (FilterFormBuilderInterface $builder) use ($addField): void {
            if ($addField) {
                $builder->single(TextType::class);
            }
        });

        $filterSet = $this->createFilterSet([
            'k' => new Filter(element: $element, type: 'test_element', alias: $alias),
        ]);

        $this->assertSame([], $filterSet->getMounts());
        $this->assertNull($filterSet->getFilterMount('k'));
        $this->assertNull($filterSet->getMount('k'));
    }

    /**
     * @return iterable<string, array{string, bool, bool}>
     */
    public static function provideUnmountedFilters(): iterable
    {
        yield 'invalid alias' => ['_.tl_flare_filter.1', false, true];
        yield 'no declared fields' => ['suche', false, false];
        yield 'cancelled build' => ['suche', true, true];
    }

    public function testGetMountIsNullWhenAListenerRemovedTheMountedChild(): void
    {
        // A FilterSetBuildEvent listener may drop children. The map still lists the filter — the
        // mount is resolved against the root form on every call, so it simply reports null.
        $this->eventDispatcher->addListener(
            FilterSetBuildEvent::class,
            static function (FilterSetBuildEvent $event): void {
                $event->formBuilder->remove('suche');
            },
        );

        $element = $this->element(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class);
        });

        $filterSet = $this->createFilterSet([
            'k' => new Filter(element: $element, type: 'test_element', alias: 'suche'),
        ]);

        $this->assertFalse($filterSet->getForm()->has('suche'));
        $this->assertSame('suche', $filterSet->getFilterMount('k')?->alias);
        $this->assertNull($filterSet->getMount('k'));
    }

    public function testGetMountIsNullForAnUnknownKey(): void
    {
        $this->assertNull($this->createFilterSet([])->getMount('nope'));
        $this->assertNull($this->createFilterSet([])->getFilterMount('nope'));
    }
}
