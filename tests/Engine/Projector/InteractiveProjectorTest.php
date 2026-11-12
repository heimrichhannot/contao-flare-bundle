<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Projector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Engine\Factory\EntryCollectorFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\AggregationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\Factory\ValidationIntentFactory;
use HeimrichHannot\FlareBundle\Engine\Intent\InteractiveIntent;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlContext;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementContext;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterCompoundFactory;
use HeimrichHannot\FlareBundle\Filter\FilterCompound;
use HeimrichHannot\FlareBundle\Filter\FilterEffectBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\Factory\FilterFormBuilderFactory;
use HeimrichHannot\FlareBundle\Form\Factory\FormHarnessFactory;
use HeimrichHannot\FlareBundle\Form\FilterFormBuilderInterface;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\List\ListCompound;
use HeimrichHannot\FlareBundle\Paginator\Factory\PaginatorFactory;
use HeimrichHannot\FlareBundle\Paginator\PaginatorConfig;
use HeimrichHannot\FlareBundle\Query\Executor\FilterInvoker;
use HeimrichHannot\FlareBundle\Query\Executor\ListQueryDirector;
use HeimrichHannot\FlareBundle\Query\Factory\FilterQueryBuilderFactory;
use HeimrichHannot\FlareBundle\Query\Factory\ListExecutionContextFactory;
use HeimrichHannot\FlareBundle\Query\Factory\QueryBuilderFactory;
use HeimrichHannot\FlareBundle\Reader\Factory\ReaderUrlGeneratorFactory;
use HeimrichHannot\FlareBundle\Registry\FilterControlRegistry;
use HeimrichHannot\FlareBundle\Registry\FilterElementRegistry;
use HeimrichHannot\FlareBundle\Registry\ProjectorRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterEffectsTrait;
use HeimrichHannot\FlareBundle\Tests\Fixture\SpyTagger;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Runs the interactive projector over the real aggregation projector, loaders and query director.
 * The tested lists never reach the database: they fail, or their filter aborts.
 */
final class InteractiveProjectorTest extends TestCase
{
    use BuildsFilterEffectsTrait;

    /** @var list<array{string, string}> */
    private array $logs = [];

    public function testValuesComeFromTheHarness(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new FilterCompound(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([]), KeywordsValue::class),
            alias: 'q',
            control: new ControlBinding(
                new KeywordsFilterControl(),
                KeywordsFilterControl::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
            value: new KeywordsValue('initial'),
        ));

        $projector = $this->createProjector(query: ['f' => ['q' => 'submitted']]);
        $view = $projector->project($list, $this->intent());

        self::assertFalse($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame('submitted', $spy->received?->keywords, 'The aggregation counts the submission.');
        self::assertSame(['q'], \array_keys($projector->loaderConfig->filterValues));
        self::assertSame('submitted', $projector->loaderConfig->filterValues['q']?->keywords);
    }

    public function testUnsubmittedHarnessDecodesTheInitialValue(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new FilterCompound(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([]), KeywordsValue::class),
            alias: 'q',
            control: new ControlBinding(
                new KeywordsFilterControl(),
                KeywordsFilterControl::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
            value: new KeywordsValue('initial'),
        ));

        $this->createProjector()->project($list, $this->intent());

        self::assertSame('initial', $spy->received?->keywords);
    }

    public function testProjectorReturnsFailedViewWhenAFixedFilterFails(): void
    {
        $list = $this->list(new FilterCompound(
            element: new ElementBinding(new ProjectorFailingElement(), 'test_element', new ComponentConfig([]), null),
            alias: 'fixed',
        ));

        $view = $this->createProjector()->project($list, $this->intent());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertCount(0, $view->getEntries());
        self::assertSame(0, $view->getPaginator()->getTotalItems());
        self::assertSame('error', $this->logs[0][0] ?? null);
        self::assertStringContainsString('broken element', $this->logs[0][1]);
    }

    public function testListFailuresRecordedAtBuildTimeYieldTheFailedView(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new FilterCompound(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([]), null),
            alias: 'fixed',
        ))
            ->withFailure(new FilterFailure('tl_flare_filter.3', 'tl_flare_filter.3: broken config'));

        $view = $this->createProjector()->project($list, $this->intent());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame(0, $spy->calls);
        self::assertStringContainsString('tl_flare_filter.3: broken config', $this->logs[0][1] ?? '');
    }

    public function testFailuresAreRethrownInDebugMode(): void
    {
        $list = $this->list(new FilterCompound(
            element: new ElementBinding(new ProjectorFailingElement(), 'test_element', new ComponentConfig([]), null),
            alias: 'fixed',
        ));

        $this->expectException(FilterException::class);

        $this->createProjector(debug: true)->project($list, $this->intent());
    }

    public function testInvalidSubmissionIsNotCounted(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(self::controlled($spy, 'q', new ProjectorRejectingControl()));

        $view = $this->createProjector(query: ['f' => ['q' => 'bad']])->project($list, $this->intent());

        self::assertFalse($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertCount(0, $view->getEntries());
        self::assertSame(0, $view->getPaginator()->getTotalItems());
        self::assertSame(0, $spy->calls, 'Neither counted nor loaded.');
        self::assertFalse($view->getForm()->isValid());
    }

    public function testHarnessFailureYieldsTheFailedView(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(self::controlled(new ProjectorSpyElement(), 'broken', new ProjectorUnbuildableControl()))
            ->withFilter(self::controlled($spy, 'q', self::keywords()));

        $view = $this->createProjector()->project($list, $this->intent());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $spy->calls);
        self::assertTrue($view->getForm()->has('q'), 'The other filters still render.');
        self::assertFalse($view->getForm()->has('broken'));
    }

    /**
     * @dataProvider provideFailures
     */
    public function testFailureWinsOverAnInvalidSubmission(bool $atBuildTime): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(self::controlled($spy, 'q', new ProjectorRejectingControl()));
        $list = $atBuildTime
            ? $list->withFailure(new FilterFailure('tl_flare_filter.3', 'broken config'))
            : $list->withFilter(
                self::controlled(new ProjectorSpyElement(), 'broken', new ProjectorUnbuildableControl()),
            );

        $view = $this->createProjector(query: ['f' => ['q' => 'bad']])->project($list, $this->intent());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $spy->calls, 'No count query ran.');
    }

    public static function provideFailures(): iterable
    {
        yield 'recorded at build time' => [true];
        yield 'control cannot build its form' => [false];
    }

    public function testHarnessFailureIsRethrownInDebugMode(): void
    {
        $list = $this->list(self::controlled(new ProjectorSpyElement(), 'broken', new ProjectorUnbuildableControl()));

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('cannot build');

        $this->createProjector(debug: true)->project($list, $this->intent());
    }

    private static function keywords(): KeywordsFilterControl
    {
        return new KeywordsFilterControl();
    }

    private static function controlled(
        FilterElementInterface $element,
        string                 $alias,
        FilterControlInterface $control,
    ): FilterCompound {
        return new FilterCompound(
            element: new ElementBinding($element, 'test_element', new ComponentConfig([]), null),
            alias: $alias,
            control: new ControlBinding(
                $control,
                $control::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
        );
    }

    private function intent(): InteractiveIntent
    {
        return new InteractiveIntent(paginatorConfig: new PaginatorConfig(itemsPerPage: 10), formName: 'f');
    }

    private function list(FilterCompound $filter): ListCompound
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ListCompound(
            driver: $driver,
            type: 'test_list',
            dc: 'tl_test',
            filters: [$filter->alias => $filter],
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    private function createProjector(array $query = [], bool $debug = false): RecordingInteractiveProjector
    {
        $logs = &$this->logs;
        $logger = new class ($logs) extends AbstractLogger {
            public function __construct(private array &$logs) {}

            public function log($level, $message, array $context = []): void
            {
                $this->logs[] = [(string) $level, (string) $message];
            }
        };

        $request = Request::create('/list', 'GET', $query);
        $request->attributes->set('_route', 'test_route');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $dispatcher = new EventDispatcher();
        $connection = $this->createMock(Connection::class);
        $director = new ListQueryDirector(
            eventDispatcher: $dispatcher,
            filterInvoker: new FilterInvoker(
                $this->createFilterEffectFactory(),
                new FilterQueryBuilderFactory($connection),
            ),
            listExecutionContextFactory: new ListExecutionContextFactory($dispatcher),
            queryBuilderFactory: new QueryBuilderFactory($this->createMock(ValidatorInterface::class), $connection),
            logger: $logger,
        );

        $filterCompoundFactory = new FilterCompoundFactory(
            filterElementRegistry: new FilterElementRegistry(),
            filterControlRegistry: new FilterControlRegistry(),
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), $dispatcher),
        );

        $aggregationProjector = new AggregationProjector();
        $loaderFactory = new LoaderFactory($filterCompoundFactory, $director);

        $container = new ServiceLocator([
            ListQueryDirector::class => static fn () => $director,
            LoaderFactory::class => static fn () => $loaderFactory,
            ProjectorRegistry::class => static fn () => new ProjectorRegistry([$aggregationProjector]),
            ReaderUrlGeneratorFactory::class => static fn () => new ReaderUrlGeneratorFactory($dispatcher),
            EntryCollectorFactory::class => static fn () => new EntryCollectorFactory(
                $dispatcher,
                $loaderFactory,
                new ReaderUrlGeneratorFactory($dispatcher),
                new SpyTagger(),
            ),
            RequestStack::class => static fn () => $requestStack,
            LoggerInterface::class => static fn () => $logger,
            ParameterBagInterface::class => static fn () => new ParameterBag(['kernel.debug' => $debug]),
        ]);

        $aggregationProjector->setContainer($container);

        $formFactory = Forms::createFormFactoryBuilder()
            ->addExtension(new CsrfExtension(new CsrfTokenManager()))
            ->addExtension(new HttpFoundationExtension())
            ->getFormFactory();

        $projector = new RecordingInteractiveProjector(
            aggregationIntentFactory: new AggregationIntentFactory(),
            formHarnessFactory: new FormHarnessFactory(
                $dispatcher,
                new FilterFormBuilderFactory($dispatcher, $formFactory),
                $formFactory,
                $logger,
                $this->createMock(TranslatorInterface::class),
            ),
            paginatorFactory: new PaginatorFactory($requestStack, $this->createMock(UrlGeneratorInterface::class)),
            validationIntentFactory: new ValidationIntentFactory($this->createMock(ValidatorInterface::class)),
        );
        $projector->setContainer($container);

        return $projector;
    }
}

final class RecordingInteractiveProjector extends InteractiveProjector
{
    public ?InteractiveLoaderConfig $loaderConfig = null;

    protected function createLoader(InteractiveLoaderConfig $config): InteractiveLoaderInterface
    {
        $this->loaderConfig = $config;

        return parent::createLoader($config);
    }
}

/**
 * Records the value it is invoked with, then aborts so that no query is executed.
 */
final class ProjectorSpyElement implements FilterElementInterface
{
    public ?ValueInterface $received = null;
    public int $calls = 0;

    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        $this->received = $value;
        ++$this->calls;

        $builder->abort();
    }
}

final class ProjectorFailingElement implements FilterElementInterface
{
    public function buildEffect(
        FilterEffectBuilderInterface $builder,
        ElementContext               $context,
        ?ValueInterface              $value,
    ): void {
        throw new FilterException('broken element');
    }
}

/**
 * A text field whose every submission is rejected.
 */
final class ProjectorRejectingControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        $builder->single(TextType::class, ['required' => false]);
    }

    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        if ($data !== null && $data !== '') {
            throw new FilterException('rejected');
        }

        return null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}

final class ProjectorUnbuildableControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlContext $context): void
    {
        throw new FilterException('This control cannot build its form.');
    }

    public function decode(mixed $data, ControlContext $context): ?KeywordsValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlContext $context): mixed
    {
        return null;
    }
}
