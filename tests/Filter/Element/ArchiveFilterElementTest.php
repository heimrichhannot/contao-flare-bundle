<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ComponentConfig;
use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerMap;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaBuilder;
use HeimrichHannot\FlareBundle\DataContainer\Builder\DcaContext;
use HeimrichHannot\FlareBundle\Model\ListModel;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Control\FilterControlInterface;
use HeimrichHannot\FlareBundle\Filter\ControlBinding;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Filter\ElementScope;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\ArchivePredicate;
use HeimrichHannot\FlareBundle\Filter\Predicate\BelongsToRelationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Contao\Model;
use Contao\Model\Collection;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

final class ArchiveFilterElementTest extends TestCase
{
    use BuildsFilterContextsTrait;
    use StubsContaoDcaTrait;

    private const GROUPS = [
        ['table' => 'tl_news_archive', 'ids' => [1, 2], 'label' => null],
        ['table' => 'tl_calendar', 'ids' => [7], 'label' => '%title%'],
    ];

    protected function setUp(): void
    {
        $this->stubDca('tl_news', ['config' => ['ptable' => 'tl_news_archive']]);
        $this->stubDca('tl_content', ['config' => ['dynamicPtable' => true]]);
        $this->stubDca('tl_orphan', ['config' => []]);
    }

    public function testTransformsPopulatedModel(): void
    {
        $config = $this->transform([
            'elm_whitelistParents' => \serialize(['3', '5', '5', '0']),
            'elm_groupWhitelistParents' => \serialize([
                ['tablePtable' => 'tl_news_archive', 'whitelistParents' => \serialize(['1']), 'formatLabel' => ''],
                ['tablePtable' => 'tl_calendar', 'whitelistParents' => ['7'], 'formatLabel' => 'custom',
                    'formatLabelCustom' => '%title%'],
                ['tablePtable' => 'tl_empty', 'whitelistParents' => ''],
            ]),
            'elm_useWhitelistForOptionsOnly' => '1',
        ]);

        self::assertSame([
            'whitelist_parents' => [3, 5],
            'group_whitelist_parents' => [
                ['table' => 'tl_news_archive', 'ids' => [1], 'label' => null],
                ['table' => 'tl_calendar', 'ids' => [7], 'label' => '%title%'],
            ],
            'use_whitelist_for_options_only' => true,
        ], $config);
    }

    public function testTransformSatisfiesTheElementSchema(): void
    {
        $element = $this->createElement();

        $resolver = new OptionsResolver();
        $element->configureOptions($resolver);

        $resolved = $resolver->resolve($this->transform([
            'elm_whitelistParents' => \serialize(['2']),
        ]));

        self::assertSame([2], $resolved['whitelist_parents']);
        self::assertSame([], $resolved['group_whitelist_parents']);
        self::assertFalse($resolved['use_whitelist_for_options_only']);
    }

    public function testNoValueFiltersByTheWholeWhitelist(): void
    {
        $p = $this->buildStatic([3, 5], false)->formula->propositions;

        self::assertCount(1, $p);
        self::assertSame(ArchivePredicate::class, $p[0]->predicateClass);
        self::assertSame(['field' => 'pid', 'parent_ids' => [3, 5]], $p[0]->options);
    }

    public function testNoValueAddsNothingWhenTheWhitelistIsForOptionsOnly(): void
    {
        self::assertSame([], $this->buildStatic([3, 5], true)->formula->propositions);
    }

    public function testNoValueAndNoWhitelistAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildStatic([], false);
    }

    /**
     * @dataProvider provideOptionsOnly
     */
    public function testSelectionIsIntersectedWithTheWhitelist(bool $optionsOnly): void
    {
        $p = $this->buildStatic([3, 5], $optionsOnly, [
            ContaoModelChoice::ref('tl_news_archive', 5),
            ContaoModelChoice::ref('tl_news_archive', 9),
        ])->formula->propositions;

        self::assertSame([5], $p[0]->options['parent_ids']);
    }

    /**
     * @dataProvider provideOptionsOnly
     */
    public function testStaleSelectionAbortsInsteadOfWidening(bool $optionsOnly): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildStatic([3, 5], $optionsOnly, [ContaoModelChoice::ref('tl_news_archive', 99)]);
    }

    public function testSelectionFromAnotherTableAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildStatic([3, 5], false, [ContaoModelChoice::ref('tl_calendar', 5)]);
    }

    public function testFixedValueAppliesInValidation(): void
    {
        $context = $this->buildFilterContext(
            $this->createElement(),
            ['whitelist_parents' => [3, 5], 'use_whitelist_for_options_only' => true],
            new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 3)]),
            new ValidationContext(),
            'tl_news',
        );

        self::assertSame([3], $context->formula->propositions[0]->options['parent_ids']);
    }

    public function testDynamicSelectionBecomesGroupedSubmittedData(): void
    {
        $p = $this->buildDynamic(false, [
            ContaoModelChoice::ref('tl_news_archive', 2),
            ContaoModelChoice::ref('tl_news_archive', 3),
            ContaoModelChoice::ref('tl_calendar', 7),
        ])->formula->propositions;

        self::assertCount(1, $p);
        self::assertSame(BelongsToRelationPredicate::class, $p[0]->predicateClass);
        self::assertSame('pid', $p[0]->options['field_pid']);
        self::assertSame('ptable', $p[0]->options['field_dynamic_ptable']);
        self::assertSame([
            ['table' => 'tl_news_archive', 'ids' => [1, 2]],
            ['table' => 'tl_calendar', 'ids' => [7]],
        ], $p[0]->options['parent_groups']);
        self::assertSame(['tl_calendar' => [7], 'tl_news_archive' => [2]], $p[0]->options['submitted_data']);
    }

    public function testDynamicNoValueFiltersByAllGroups(): void
    {
        $p = $this->buildDynamic(false)->formula->propositions;

        self::assertSame(['tl_news_archive' => [1, 2], 'tl_calendar' => [7]], $p[0]->options['submitted_data']);
    }

    public function testDynamicNoValueAddsNothingWhenTheWhitelistIsForOptionsOnly(): void
    {
        self::assertSame([], $this->buildDynamic(true)->formula->propositions);
    }

    public function testDynamicStaleSelectionAborts(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildDynamic(true, [ContaoModelChoice::ref('tl_calendar', 1)]);
    }

    public function testListWithoutPtableFails(): void
    {
        $this->expectException(FilterException::class);

        $this->buildFilterContext($this->createElement(), ['whitelist_parents' => [3]], null, null, 'tl_orphan');
    }

    public function testListWithoutPtableFailsWithoutControlToo(): void
    {
        $this->expectException(FilterException::class);

        $this->createFilterContextFactory()->create(
            $this->buildElementScope($this->createElement(), [], null, 'tl_orphan'),
            new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 5)]),
        );
    }

    public function testWithoutControlTheValueAppliesUnboundedByTheWhitelist(): void
    {
        $p = $this->buildStatic([3], false, [
            ContaoModelChoice::ref('tl_news_archive', 5),
            ContaoModelChoice::ref('tl_news_archive', 9),
        ], controlled: false)->formula->propositions;

        self::assertCount(1, $p);
        self::assertSame(ArchivePredicate::class, $p[0]->predicateClass);
        self::assertSame(['field' => 'pid', 'parent_ids' => [5, 9]], $p[0]->options);
    }

    public function testWithoutControlAndValueNothingIsRestricted(): void
    {
        self::assertSame([], $this->buildStatic([3, 5], false, controlled: false)->formula->propositions);
        self::assertSame([], $this->buildDynamic(false, controlled: false)->formula->propositions);
    }

    public function testWithoutControlForeignTableKeysAbort(): void
    {
        $this->expectException(AbortFilteringException::class);

        $this->buildStatic([3, 5], false, [ContaoModelChoice::ref('tl_calendar', 5)], controlled: false);
    }

    public function testDynamicWithoutControlGroupsTheValueByTable(): void
    {
        $p = $this->buildDynamic(false, [
            ContaoModelChoice::ref('tl_news', 1),
            ContaoModelChoice::ref('tl_calendar', 7),
            ContaoModelChoice::ref('tl_calendar', 8),
        ], controlled: false)->formula->propositions;

        self::assertCount(1, $p);
        self::assertSame(BelongsToRelationPredicate::class, $p[0]->predicateClass);
        // A ChoiceValue is sorted by canonical key, so the groups follow the table names.
        self::assertSame([
            ['table' => 'tl_calendar', 'ids' => [7, 8]],
            ['table' => 'tl_news', 'ids' => [1]],
        ], $p[0]->options['parent_groups']);
        self::assertNull($p[0]->options['submitted_data']);
    }

    public function testOnlyAFilterWithoutControlAndValueIsIneffective(): void
    {
        $element = $this->createElement();
        $filter = $this->buildElementScope(
            $element,
            ['whitelist_parents' => [1], 'use_whitelist_for_options_only' => true],
            dc: 'tl_news',
        )->filter;

        $reason = $element->getIneffectiveReason($filter);

        self::assertNotNull($reason);
        self::assertSame(
            'filter.info.ineffective.archive_without_value',
            $reason->trans(new class implements TranslatorInterface {
                use TranslatorTrait;
            }),
        );

        $withValue = $filter->withValue(new ChoiceValue([ContaoModelChoice::ref('tl_news_archive', 1)]));
        self::assertNull($element->getIneffectiveReason($withValue), 'the fixed value restricts the list');

        $controlled = $filter->withControl(new ControlBinding(
            $this->createMock(FilterControlInterface::class),
            'flare_choice',
            new ComponentConfig(),
        ));
        self::assertNull($element->getIneffectiveReason($controlled), 'the control restricts the list');
    }

    public function testWithoutControlChoicesAreAllRecordsOfThePtable(): void
    {
        $element = $this->recordingElement(['tl_news_archive' => [3, 5, 9]]);
        $scope = $this->buildElementScope($element, ['whitelist_parents' => [3]], null, 'tl_news');

        self::assertSame(
            ['tl_news_archive.3', 'tl_news_archive.5', 'tl_news_archive.9'],
            $this->choiceKeys($element, $scope),
        );
        self::assertSame([['all', 'tl_news_archive']], $element->calls, 'the whitelist is not read');
    }

    public function testWithoutControlDynamicChoicesComeFromTheDistinctPtables(): void
    {
        $element = $this->recordingElement(
            ['tl_news' => [1], 'tl_calendar' => [7]],
            ['tl_news', 'tl_unknown', 'tl_calendar'],
        );
        $scope = $this->buildElementScope($element, ['group_whitelist_parents' => self::GROUPS], null, 'tl_content');

        self::assertSame(['tl_news.1', 'tl_calendar.7'], $this->choiceKeys($element, $scope));
        self::assertSame(
            [['tables', 'tl_content'], ['all', 'tl_news'], ['all', 'tl_unknown'], ['all', 'tl_calendar']],
            $element->calls,
        );
    }

    public function testWithoutControlAndNoParentsTheChoicesAreEmpty(): void
    {
        $static = $this->recordingElement([]);
        $dynamic = $this->recordingElement([], []);

        self::assertSame([], $this->choiceKeys($static, $this->buildElementScope($static, [], null, 'tl_news')));
        self::assertSame([], $this->choiceKeys($dynamic, $this->buildElementScope($dynamic, [], null, 'tl_content')));
    }

    public function testWithControlChoicesStayTheWhitelist(): void
    {
        $element = $this->recordingElement(['tl_news_archive' => [3, 5, 9]]);
        $element->setTranslator(self::prefixingTranslator());
        $scope = $this->controlled($this->buildElementScope($element, ['whitelist_parents' => [3]], null, 'tl_news'));

        try
        {
            $this->choiceKeys($element, $scope);
            self::fail('The stub has no whitelisted parents, so the controlled path throws.');
        }
        catch (FilterException $e)
        {
            self::assertSame([['whitelist', 'tl_news_archive', [3]]], $element->calls);
            self::assertSame('flare: errors.archive.no_parents', $e->getMessage(), 'the backend shows it to editors');
        }
    }

    public function testChoicesOfAListWithoutPtableFailWithATranslatedMessage(): void
    {
        $element = $this->recordingElement([]);
        $element->setTranslator(self::prefixingTranslator());

        $this->expectException(FilterException::class);
        $this->expectExceptionMessage('flare: errors.archive.no_ptable');

        $this->choiceKeys($element, $this->buildElementScope($element, [], null, 'tl_orphan'));
    }

    public function testWithoutATranslatorTheMessageIsTheKey(): void
    {
        $this->expectExceptionMessage('errors.archive.no_ptable');

        $this->buildFilterContext($this->createElement(), [], null, null, 'tl_orphan');
    }

    public function testDynamicParentTablesAreTheDistinctNonEmptyPtablesOfTheDataContainer(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn (string $id): string => "`{$id}`");
        $connection->expects(self::once())
            ->method('fetchFirstColumn')
            ->with("SELECT DISTINCT `ptable` FROM `tl_content` WHERE `ptable` <> ''")
            ->willReturn(['tl_news', 'tl_calendar']);

        $element = $this->createElement();
        $element->setConnection($connection);

        self::assertSame(
            ['tl_news', 'tl_calendar'],
            (new \ReflectionMethod($element, 'fetchDynamicParentTables'))->invoke($element, 'tl_content'),
        );
    }

    public function testAllParentChoicesAreEveryRecordOfTheTablesModel(): void
    {
        $GLOBALS['TL_MODELS']['tl_archive_stub'] = ArchiveStubModel::class;
        ArchiveStubModel::$rows = [['id' => 4], ['id' => 6]];

        try
        {
            $fetch = new \ReflectionMethod(ArchiveFilterElement::class, 'fetchAllParentChoices');
            $choices = $fetch->invoke($this->createElement(), 'tl_archive_stub');

            self::assertSame(
                [['tl_archive_stub', 4], ['tl_archive_stub', 6]],
                \array_map(static fn (ContaoModelChoice $c): array => [$c->table, $c->id], $choices),
            );
            self::assertSame([], $fetch->invoke($this->createElement(), 'tl_without_model'));
        }
        finally
        {
            unset($GLOBALS['TL_MODELS']['tl_archive_stub']);
        }
    }

    public function testWhitelistFieldsAreOnlyShownWithAControl(): void
    {
        $palette = function (string $control): ?string {
            $list = (new \ReflectionClass(ListModel::class))->newInstanceWithoutConstructor();
            (function (): void {
                $this->arrData = ['id' => 1, 'dc' => 'tl_news'];
            })->call($list);

            $builder = new DcaBuilder();
            $this->createElement()->buildDca($builder, new DcaContext(
                table: 'tl_flare_filter',
                type: ArchiveFilterElement::TYPE,
                listModel: $list,
                filterModel: new FilterModelStub(['id' => 7, 'pid' => 1, 'control' => $control]),
                executionContextFactory: static fn () => null,
            ));

            return $builder->getPalette();
        };

        self::assertNull($palette(''), 'without a control the value alone restricts');
        self::assertStringContainsString('elm_whitelistParents', (string) $palette('flare_choice'));
        self::assertStringContainsString('elm_useWhitelistForOptionsOnly', (string) $palette('flare_choice'));
    }

    public function testChoiceFromKeyParsesTableAndId(): void
    {
        $el = $this->createElement();

        $ref = $el->choiceFromKey('tl_news_archive.5');
        self::assertInstanceOf(ContaoModelChoice::class, $ref);
        self::assertSame(['tl_news_archive', 5], [$ref->table, $ref->id]);

        self::assertNull($el->choiceFromKey('5'));
        self::assertNull($el->choiceFromKey('tl_x.abc'));
        self::assertNull($el->choiceFromKey('tl_x.0'));
        self::assertNull($el->choiceFromKey('tl_x.-3'));
        self::assertNull($el->choiceFromKey('tl_x.5.1'));
        self::assertNull($el->choiceFromKey('.5'));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function provideOptionsOnly(): iterable
    {
        yield 'whitelist restricts the list' => [false];
        yield 'whitelist for options only' => [true];
    }

    /**
     * @param list<int> $whitelist
     * @param list<ContaoModelChoice> $selected
     */
    private function buildStatic(
        array $whitelist,
        bool  $optionsOnly,
        array $selected = [],
        bool  $controlled = true,
    ): FilterContext {
        return $this->build(
            ['whitelist_parents' => $whitelist, 'use_whitelist_for_options_only' => $optionsOnly],
            $selected,
            'tl_news',
            $controlled,
        );
    }

    /**
     * @param list<ContaoModelChoice> $selected
     */
    private function buildDynamic(bool $optionsOnly, array $selected = [], bool $controlled = true): FilterContext
    {
        return $this->build(
            ['group_whitelist_parents' => self::GROUPS, 'use_whitelist_for_options_only' => $optionsOnly],
            $selected,
            'tl_content',
            $controlled,
        );
    }

    /**
     * @param array<string, mixed> $config
     * @param list<ContaoModelChoice> $selected
     */
    private function build(array $config, array $selected, string $dc, bool $controlled): FilterContext
    {
        $scope = $this->buildElementScope($this->createElement(), $config, null, $dc);

        return $this->createFilterContextFactory()->create(
            $controlled ? $this->controlled($scope) : $scope,
            $selected ? new ChoiceValue($selected) : null,
        );
    }

    /**
     * The scope of the same filter with a control: the whitelist then provides the options and bounds the value.
     */
    private function controlled(ElementScope $scope): ElementScope
    {
        return new ElementScope(
            $scope->list,
            $scope->filter->withControl(new ControlBinding(
                $this->createMock(FilterControlInterface::class),
                'flare_choice',
                new ComponentConfig(),
            )),
            $scope->engineContext,
        );
    }

    /**
     * An element whose parent lookups are served from the given ids and recorded in `$calls`.
     *
     * @param array<string, list<int>> $parents Parent ids by table.
     * @param list<string> $dynamicTables The tables the dynamic ptable column holds.
     */
    private function recordingElement(array $parents, array $dynamicTables = []): ArchiveFilterElement
    {
        return new class ($parents, $dynamicTables) extends ArchiveFilterElement {
            /** @var list<array<mixed>> */
            public array $calls = [];

            public function __construct(private readonly array $parents, private readonly array $dynamicTables) {}

            protected function fetchAllParentChoices(string $table): array
            {
                $this->calls[] = ['all', $table];

                return \array_map(
                    static fn (int $id): ContaoModelChoice => ContaoModelChoice::ref($table, $id),
                    $this->parents[$table] ?? [],
                );
            }

            protected function fetchDynamicParentTables(string $table): array
            {
                $this->calls[] = ['tables', $table];

                return $this->dynamicTables;
            }

            protected function fetchParents(?string $table, array $ids): ?Collection
            {
                $this->calls[] = ['whitelist', $table, $ids];

                return null;
            }
        };
    }

    /**
     * A translator that prefixes each key with its domain, so a test sees which key in which domain was translated.
     */
    private static function prefixingTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            use TranslatorTrait {
                trans as private translate;
            }

            public function trans(
                ?string $id,
                array   $parameters = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                return "{$domain}: " . $this->translate($id, $parameters, $domain, $locale);
            }
        };
    }

    /**
     * @return list<string>
     */
    private function choiceKeys(ArchiveFilterElement $element, ElementScope $scope): array
    {
        $choices = (new ChoicesBuilderFactory(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ParameterBagInterface::class),
        ))->createChoicesBuilder();

        $element->buildChoices($choices, $scope);

        return \array_map('strval', \array_keys($choices->build()));
    }

    private function createElement(): ArchiveFilterElement
    {
        return new ArchiveFilterElement();
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(array $row): array
    {
        $element = $this->createElement();

        $transformers = new TransformerMap();
        $element->configureTransformers($transformers);

        $transformer = $transformers->find($model = new FilterModelStub($row));
        self::assertNotNull($transformer);

        $transformer($config = new ConfigBuilder(), $model);

        return $config->all();
    }
}

/**
 * A model of the table `tl_archive_stub` whose findAll() serves `$rows` without a database.
 */
final class ArchiveStubModel extends Model
{
    /** @var list<array<string, mixed>> */
    public static array $rows = [];

    protected static $strTable = 'tl_archive_stub';

    public static function findAll(array $arrOptions = []): ?Collection
    {
        $models = \array_map(static function (array $row): self {
            $model = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
            $model->arrData = $row;

            return $model;
        }, self::$rows);

        return $models ? new Collection($models, self::$strTable) : null;
    }
}
