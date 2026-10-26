<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Form;

use HeimrichHannot\FlareBundle\Engine\Context\ContextInterface;
use HeimrichHannot\FlareBundle\Engine\Context\FormContextInterface;
use HeimrichHannot\FlareBundle\Event\FilterControlBuildFormEvent;
use HeimrichHannot\FlareBundle\Event\FormHarnessBuildEvent;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Exception\FlareException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilder;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\FilterScope;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\Factory\FormHarnessFactory;
use HeimrichHannot\FlareBundle\Form\FilterMount;
use HeimrichHannot\FlareBundle\Form\FormHarness;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

final class FormHarnessFactoryTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private AbstractLogger $logger;

    /** @var list<array{string, string}> */
    private array $logged = [];

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->logged = [];
        $test = $this;
        $this->logger = new class($test) extends AbstractLogger {
            public function __construct(private readonly FormHarnessFactoryTest $test) {}

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->test->record((string) $level, (string) $message);
            }
        };
    }

    public function record(string $level, string $message): void
    {
        $this->logged[] = [$level, $message];
    }

    private function createFactory(): FormHarnessFactory
    {
        // The CSRF extension only needs to define the "csrf_protection" option; the factory
        // always disables it, so the token manager is never used.
        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->getFormFactory();

        return new FormHarnessFactory(
            eventDispatcher: $this->eventDispatcher,
            formFactory: $formFactory,
            logger: $this->logger,
        );
    }

    private function createForm(array $filters): FormInterface
    {
        return $this->createFilterSet($filters)->getForm();
    }

    private function createFilterSet(array $filters): FormHarness
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
     * Creates a control building its form via the given callable; it decodes to a KeywordsValue.
     *
     * @param callable(FilterFormBuilderInterface, FilterScope): void $buildForm
     */
    private function control(callable $buildForm): FilterControlInterface
    {
        return new class($buildForm) implements FilterControlInterface {
            /** @var callable */
            private $buildForm;

            public function __construct(callable $buildForm)
            {
                $this->buildForm = $buildForm;
            }

            public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
            {
                ($this->buildForm)($builder, $scope);
            }

            public function decode(mixed $data, FilterScope $scope): ?ValueInterface
            {
                return KeywordsValue::tryFrom($data);
            }

            public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
            {
                return null;
            }
        };
    }

    private function filter(string $alias, ?FilterControlInterface $control, ?ValueInterface $value = null): Filter
    {
        $element = new class implements FilterElementInterface {
            public function buildContext(FilterContextBuilder $builder, ?ValueInterface $value): void {}
        };

        return new Filter(
            element: $element,
            type: 'test_element',
            alias: $alias,
            control: $control,
            controlConfig: ['label' => null, 'placeholder' => null],
            value: $value,
        );
    }

    private function single(): FilterControlInterface
    {
        return $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
        });
    }

    public function testSingleFieldMountsFlatUnderTheAlias(): void
    {
        $control = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->setAttribute('custom.attr', 'kept');
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $form = $this->createForm([$this->filter('suche', $control)]);

        $this->assertTrue($form->has('suche'));

        $config = $form->get('suche')->getConfig();

        $this->assertInstanceOf(TextType::class, $config->getType()->getInnerType());
        $this->assertTrue($config->getAttribute(FilterScope::ATTR_SINGLE_FIELD));
        $this->assertSame('kept', $config->getAttribute('custom.attr'));
        $this->assertInstanceOf(FilterScope::class, $config->getAttribute(FilterScope::ATTR_SELF));
        $this->assertTrue(
            $config->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted builder',
        );
    }

    public function testSingleWithCompanionFieldIsRejected(): void
    {
        $control = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->single(TextType::class, ['required' => false]);
            $builder->add('extra', TextType::class, ['required' => false]);
        });

        $this->expectException(FlareException::class);
        $this->expectExceptionMessage(
            'Filter control cannot declare a single field and add children at the same time.',
        );

        $this->createForm([$this->filter('suche', $control)]);
    }

    public function testMultiFieldControlMountsNestedCompound(): void
    {
        $control = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->add('to', TextType::class);
            $builder->addEventListener(FormEvents::POST_SUBMIT, static function (): void {});
        });

        $child = $this->createForm([$this->filter('range', $control)])->get('range');

        $this->assertInstanceOf(FormType::class, $child->getConfig()->getType()->getInnerType());
        $this->assertTrue($child->has('from'));
        $this->assertTrue($child->has('to'));
        $this->assertTrue(
            $child->getConfig()->getEventDispatcher()->hasListeners(FormEvents::POST_SUBMIT),
            'Deferred listeners must be replayed onto the mounted compound',
        );
    }

    public function testCompoundMountTakesTheControlsCompoundOptions(): void
    {
        $labelled = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->compound(['label' => 'Period']);
        });
        $plain = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
        });

        $form = $this->createForm([$this->filter('range', $labelled), $this->filter('other', $plain)]);

        $this->assertSame('Period', $form->get('range')->getConfig()->getOption('label'));
        $this->assertFalse($form->get('range')->getConfig()->getOption('required'));
        $this->assertFalse($form->get('other')->getConfig()->getOption('label'));
    }

    public function testControlWithoutFieldsIsNotMounted(): void
    {
        $control = $this->control(static function (): void {});

        $this->assertFalse($this->createForm([$this->filter('empty', $control)])->has('empty'));
    }

    public function testFiltersWithoutAControlAreNotMounted(): void
    {
        $harness = $this->createFilterSet([$this->filter('fixed', null, new KeywordsValue('foo'))]);

        $this->assertFalse($harness->getForm()->has('fixed'));
        $this->assertSame([], $harness->getMounts());
        $this->assertSame([], $harness->decode());
    }

    public function testInvalidAliasIsSkipped(): void
    {
        $form = $this->createForm([$this->filter('_.tl_flare_filter.1', $this->single())]);

        $this->assertSame(0, \count($form));
    }

    public function testCancelledEventPreventsMounting(): void
    {
        $this->eventDispatcher->addListener(
            FilterControlBuildFormEvent::class,
            static fn (FilterControlBuildFormEvent $event) => $event->cancel(),
        );

        $this->assertFalse($this->createForm([$this->filter('suche', $this->single())])->has('suche'));
    }

    public function testControlFailingToBuildIsSkipped(): void
    {
        $failing = $this->control(static function (): void {
            throw new FilterException('boom');
        });

        $harness = $this->createFilterSet([
            $this->filter('broken', $failing),
            $this->filter('suche', $this->single()),
        ]);

        $this->assertFalse($harness->getForm()->has('broken'));
        $this->assertTrue($harness->getForm()->has('suche'));
        $this->assertSame(['suche'], \array_keys($harness->getMounts()));
        $this->assertCount(1, $this->logged);
        $this->assertSame('warning', $this->logged[0][0]);
        $this->assertStringContainsString('broken', $this->logged[0][1]);
    }

    public function testControlFailingToHydrateIsSkipped(): void
    {
        $control = new class implements FilterControlInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
            {
                $builder->single(TextType::class);
            }

            public function decode(mixed $data, FilterScope $scope): ?ValueInterface
            {
                return null;
            }

            public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
            {
                throw new FilterException('unusable value');
            }
        };

        $harness = $this->createFilterSet([$this->filter('suche', $control)]);

        $this->assertSame([], $harness->getMounts());
        $this->assertFalse($harness->getForm()->has('suche'));
        $this->assertCount(1, $this->logged);
    }

    public function testGetFormReturnsTheRootForm(): void
    {
        $harness = $this->createFilterSet([$this->filter('suche', $this->single())]);

        $form = $harness->getForm();

        $this->assertSame('flare_test', $form->getName());
        $this->assertTrue($form->has('suche'));
        $this->assertSame($form, $harness->getForm(), 'The root form is not rebuilt per call');
    }

    public function testMountMapRecordsSingleAndCompoundFiltersUnderTheirAlias(): void
    {
        $compound = $this->control(static function (FilterFormBuilderInterface $builder): void {
            $builder->add('from', TextType::class);
            $builder->add('to', TextType::class);
        });

        $singleFilter = $this->filter('suche', $this->single());
        $compoundFilter = $this->filter('range', $compound);

        $harness = $this->createFilterSet([$singleFilter, $compoundFilter]);

        $this->assertSame(['suche', 'range'], \array_keys($harness->getMounts()));

        $singleMount = $harness->getMount('suche');
        $this->assertInstanceOf(FilterMount::class, $singleMount);
        $this->assertSame($singleFilter, $singleMount->filter);
        $this->assertSame('suche', $singleMount->alias);
        $this->assertSame($singleFilter, $singleMount->scope->filter);

        $compoundMount = $harness->getMount('range');
        $this->assertInstanceOf(FilterMount::class, $compoundMount);
        $this->assertSame('range', $compoundMount->alias);
        $this->assertSame($compoundFilter, $compoundMount->scope->filter);
    }

    public function testGetChildResolvesTheSameChildAsTheRootForm(): void
    {
        $harness = $this->createFilterSet([$this->filter('suche', $this->single())]);

        $this->assertSame($harness->getForm()->get('suche'), $harness->getChild('suche'));
    }

    public function testGetChildToleratesALeadingDigitAlias(): void
    {
        // Str::isValidFormName() permits a leading digit, the one alias shape where PHP array-key
        // coercion turns the mount key into an int.
        $harness = $this->createFilterSet([$this->filter('0', $this->single())]);

        $this->assertSame('0', $harness->getMount('0')?->alias);
        $this->assertSame($harness->getForm()->get('0'), $harness->getChild('0'));
    }

    /**
     * @dataProvider provideUnmountedFilters
     */
    public function testUnmountedFiltersAreAbsentFromTheMountMap(string $alias, bool $cancel, bool $addField): void
    {
        if ($cancel) {
            $this->eventDispatcher->addListener(
                FilterControlBuildFormEvent::class,
                static fn (FilterControlBuildFormEvent $event) => $event->cancel(),
            );
        }

        $control = $this->control(static function (FilterFormBuilderInterface $builder) use ($addField): void {
            if ($addField) {
                $builder->single(TextType::class);
            }
        });

        $harness = $this->createFilterSet([$this->filter($alias, $control)]);

        $this->assertSame([], $harness->getMounts());
        $this->assertNull($harness->getMount($alias));
        $this->assertNull($harness->getChild($alias));
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

    public function testGetChildIsNullWhenAListenerRemovedTheMountedChild(): void
    {
        // A FormHarnessBuildEvent listener may drop children. The map still lists the filter, the
        // child is resolved against the root form on every call, so it simply reports null.
        $this->eventDispatcher->addListener(
            FormHarnessBuildEvent::class,
            static function (FormHarnessBuildEvent $event): void {
                $event->formBuilder->remove('suche');
            },
        );

        $harness = $this->createFilterSet([$this->filter('suche', $this->single())]);

        $this->assertFalse($harness->getForm()->has('suche'));
        $this->assertSame('suche', $harness->getMount('suche')?->alias);
        $this->assertNull($harness->getChild('suche'));
        $this->assertSame([], $harness->decode());
    }

    public function testGetMountAndChildAreNullForAnUnknownAlias(): void
    {
        $this->assertNull($this->createFilterSet([])->getChild('nope'));
        $this->assertNull($this->createFilterSet([])->getMount('nope'));
    }

    public function testUnsubmittedFormDecodesToTheHydratedInitialValue(): void
    {
        $harness = $this->createFilterSet([
            $this->filter('q', new KeywordsFilterControl(), new KeywordsValue('foo')),
        ]);

        $values = $harness->decode();

        $this->assertInstanceOf(KeywordsValue::class, $values['q']);
        $this->assertSame('foo', $values['q']->keywords);
    }

    public function testUnsubmittedFormWithoutInitialValueDecodesToNothing(): void
    {
        $harness = $this->createFilterSet([$this->filter('q', new KeywordsFilterControl())]);

        $this->assertSame([], $harness->decode());
    }

    public function testSubmittedValueOverridesTheInitialValue(): void
    {
        $harness = $this->createFilterSet([
            $this->filter('q', new KeywordsFilterControl(), new KeywordsValue('foo')),
        ]);

        $harness->getForm()->submit(['q' => 'bar']);

        $this->assertSame('bar', $harness->decode()['q']->keywords);
    }

    public function testSubmittedEmptyValueIsKeptAsNull(): void
    {
        $harness = $this->createFilterSet([
            $this->filter('q', new KeywordsFilterControl(), new KeywordsValue('foo')),
        ]);

        $harness->getForm()->submit(['q' => '']);

        $values = $harness->decode();

        $this->assertTrue(\array_key_exists('q', $values));
        $this->assertNull($values['q']);
    }

    public function testCompoundMountIsHydratedWithTheControlsArray(): void
    {
        $control = new class implements FilterControlInterface {
            public function buildForm(FilterFormBuilderInterface $builder, FilterScope $scope): void
            {
                $builder->add('from', TextType::class);
                $builder->add('to', TextType::class);
            }

            public function decode(mixed $data, FilterScope $scope): ?ValueInterface
            {
                return KeywordsValue::tryFrom(($data['from'] ?? '') . ' ' . ($data['to'] ?? ''));
            }

            public function hydrate(?ValueInterface $value, FilterScope $scope): mixed
            {
                return ['from' => 'a', 'to' => 'b'];
            }
        };

        $harness = $this->createFilterSet([$this->filter('range', $control, new KeywordsValue('x'))]);

        $this->assertSame('a b', $harness->decode()['range']->keywords);
    }
}
