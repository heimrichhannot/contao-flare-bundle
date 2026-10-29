<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Engine\Projector;

use Doctrine\DBAL\Connection;
use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\SchemaResolver;
use HeimrichHannot\FlareBundle\Config\SourceTransformer;
use HeimrichHannot\FlareBundle\Engine\Context\Factory\AggregationContextFactory;
use HeimrichHannot\FlareBundle\Engine\Context\InteractiveContext;
use HeimrichHannot\FlareBundle\Engine\Factory\LoaderFactory;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderConfig;
use HeimrichHannot\FlareBundle\Engine\Loader\InteractiveLoaderInterface;
use HeimrichHannot\FlareBundle\Engine\Projector\AggregationProjector;
use HeimrichHannot\FlareBundle\Engine\Projector\InteractiveProjector;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\KeywordsFilterControl;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Element\FilterElementInterface;
use HeimrichHannot\FlareBundle\Filter\ElementBinding;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\Factory\FilterFactory;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterContextBuilderInterface;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterOptionsResolver;
use HeimrichHannot\FlareBundle\Filter\Resolver\FilterTransformerResolver;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Filter\Value\ValueInterface;
use HeimrichHannot\FlareBundle\Form\Factory\FormHarnessFactory;
use HeimrichHannot\FlareBundle\Form\FilterFormInvoker;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
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
use HeimrichHannot\FlareBundle\Registry\ValueProviderRegistry;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use HeimrichHannot\FlareBundle\List\FilterFailure;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlScope;
use HeimrichHannot\FlareBundle\Filter\FilterFormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs the interactive projector over the real aggregation projector, loaders and query director.
 * The tested lists never reach the database: they fail, or their filter aborts.
 */
final class InteractiveProjectorTest extends TestCase
{
    use BuildsFilterContextsTrait;

    /** @var list<array{string, string}> */
    private array $logs = [];

    public function testValuesComeFromTheHarness(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new Filter(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([])),
            alias: 'q',
            control: new ControlBinding(
                new KeywordsFilterControl(),
                KeywordsFilterControl::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
            value: new KeywordsValue('initial'),
        ));

        $projector = $this->createProjector(query: ['f' => ['q' => 'submitted']]);
        $view = $projector->project($list, $this->context());

        self::assertFalse($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame('submitted', $spy->received?->keywords, 'The aggregation counts the submission.');
        self::assertSame(['q'], \array_keys($projector->loaderConfig->filterValues));
        self::assertSame('submitted', $projector->loaderConfig->filterValues['q']?->keywords);
    }

    public function testUnsubmittedHarnessDecodesTheInitialValue(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new Filter(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([])),
            alias: 'q',
            control: new ControlBinding(
                new KeywordsFilterControl(),
                KeywordsFilterControl::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
            value: new KeywordsValue('initial'),
        ));

        $this->createProjector()->project($list, $this->context());

        self::assertSame('initial', $spy->received?->keywords);
    }

    public function testProjectorReturnsFailedViewWhenAFixedFilterFails(): void
    {
        $list = $this->list(new Filter(
            element: new ElementBinding(new ProjectorFailingElement(), 'test_element', new ComponentConfig([])),
            alias: 'fixed',
        ));

        $view = $this->createProjector()->project($list, $this->context());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame([], $view->getEntries());
        self::assertSame(0, $view->getPaginator()->getTotalItems());
        self::assertSame('error', $this->logs[0][0] ?? null);
        self::assertStringContainsString('broken element', $this->logs[0][1]);
    }

    public function testListFailuresRecordedAtBuildTimeYieldTheFailedView(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(new Filter(
            element: new ElementBinding($spy, 'test_element', new ComponentConfig([])),
            alias: 'fixed',
        ))
            ->withFailure(new FilterFailure('tl_flare_filter.3', 'tl_flare_filter.3: broken config'));

        $view = $this->createProjector()->project($list, $this->context());

        self::assertTrue($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame(0, $spy->calls);
        self::assertStringContainsString('tl_flare_filter.3: broken config', $this->logs[0][1] ?? '');
    }

    public function testFailuresAreRethrownInDebugMode(): void
    {
        $list = $this->list(new Filter(
            element: new ElementBinding(new ProjectorFailingElement(), 'test_element', new ComponentConfig([])),
            alias: 'fixed',
        ));

        $this->expectException(FilterException::class);

        $this->createProjector(debug: true)->project($list, $this->context());
    }

    public function testInvalidSubmissionIsNotCounted(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(self::controlled($spy, 'q', new ProjectorRejectingControl()));

        $view = $this->createProjector(query: ['f' => ['q' => 'bad']])->project($list, $this->context());

        self::assertFalse($view->isFailed());
        self::assertSame(0, $view->getCount());
        self::assertSame([], $view->getEntries());
        self::assertSame(0, $view->getPaginator()->getTotalItems());
        self::assertSame(0, $spy->calls, 'Neither counted nor loaded.');
        self::assertFalse($view->getForm()->isValid());
    }

    public function testHarnessFailureYieldsTheFailedView(): void
    {
        $spy = new ProjectorSpyElement();
        $list = $this->list(self::controlled(new ProjectorSpyElement(), 'broken', new ProjectorUnbuildableControl()))
            ->withFilter(self::controlled($spy, 'q', self::keywords()));

        $view = $this->createProjector()->project($list, $this->context());

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

        $view = $this->createProjector(query: ['f' => ['q' => 'bad']])->project($list, $this->context());

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

        $this->createProjector(debug: true)->project($list, $this->context());
    }

    private static function keywords(): KeywordsFilterControl
    {
        return new KeywordsFilterControl();
    }

    private static function controlled(
        FilterElementInterface $element,
        string                 $alias,
        FilterControlInterface $control,
    ): Filter {
        return new Filter(
            element: new ElementBinding($element, 'test_element', new ComponentConfig([])),
            alias: $alias,
            control: new ControlBinding(
                $control,
                $control::class,
                new ComponentConfig(['label' => null, 'placeholder' => null]),
            ),
        );
    }

    private function context(): InteractiveContext
    {
        return new InteractiveContext(paginatorConfig: new PaginatorConfig(itemsPerPage: 10), formName: 'f');
    }

    private function list(Filter $filter): ListSpec
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        return new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test', filters: [$filter->alias => $filter]);
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
                $this->createFilterContextFactory(),
                new FilterQueryBuilderFactory($connection),
            ),
            listExecutionContextFactory: new ListExecutionContextFactory($dispatcher),
            queryBuilderFactory: new QueryBuilderFactory($this->createMock(ValidatorInterface::class), $connection),
            logger: $logger,
        );

        $filterFactory = new FilterFactory(
            filterElementRegistry: new FilterElementRegistry(),
            filterControlRegistry: new FilterControlRegistry(),
            valueProviderRegistry: new ValueProviderRegistry(),
            filterOptionsResolver: new FilterOptionsResolver(new SchemaResolver()),
            filterTransformerResolver: new FilterTransformerResolver(new SourceTransformer(), $dispatcher),
            logger: $logger,
        );

        $aggregationProjector = new AggregationProjector();

        $container = new ServiceLocator([
            ListQueryDirector::class => static fn () => $director,
            LoaderFactory::class => static fn () => new LoaderFactory($filterFactory, $director),
            ProjectorRegistry::class => static fn () => new ProjectorRegistry([$aggregationProjector]),
            ReaderUrlGeneratorFactory::class => static fn () => new ReaderUrlGeneratorFactory($dispatcher),
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
            aggregationConfigFactory: new AggregationContextFactory(),
            formHarnessFactory: new FormHarnessFactory(
                $dispatcher,
                new FilterFormInvoker($dispatcher, $formFactory),
                $formFactory,
                $logger,
                $this->createMock(TranslatorInterface::class),
            ),
            paginatorFactory: new PaginatorFactory($requestStack, $this->createMock(UrlGeneratorInterface::class)),
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

    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        $this->received = $value;
        ++$this->calls;

        $builder->abort();
    }
}

final class ProjectorFailingElement implements FilterElementInterface
{
    public function buildContext(
        FilterContextBuilderInterface $builder,
        ElementScope                  $scope,
        ?ValueInterface               $value,
    ): void {
        throw new FilterException('broken element');
    }
}

/**
 * A text field whose every submission is rejected.
 */
final class ProjectorRejectingControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        $builder->single(TextType::class, ['required' => false]);
    }

    public function decode(mixed $data, ControlScope $scope): ?KeywordsValue
    {
        if ($data !== null && $data !== '') {
            throw new FilterException('rejected');
        }

        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}

final class ProjectorUnbuildableControl implements FilterControlInterface
{
    public function buildForm(FilterFormBuilderInterface $builder, ControlScope $scope): void
    {
        throw new FilterException('This control cannot build its form.');
    }

    public function decode(mixed $data, ControlScope $scope): ?KeywordsValue
    {
        return null;
    }

    public function hydrate(?ValueInterface $value, ControlScope $scope): mixed
    {
        return null;
    }
}
