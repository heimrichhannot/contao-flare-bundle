<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Element;

use HeimrichHannot\FlareBundle\Config\ConfigBuilder;
use HeimrichHannot\FlareBundle\Config\TransformerResolver;
use HeimrichHannot\FlareBundle\Engine\Context\ValidationContext;
use HeimrichHannot\FlareBundle\Exception\AbortFilteringException;
use HeimrichHannot\FlareBundle\Exception\FilterException;
use HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement;
use HeimrichHannot\FlareBundle\Filter\FilterContext;
use HeimrichHannot\FlareBundle\Filter\Predicate\ArchivePredicate;
use HeimrichHannot\FlareBundle\Filter\Predicate\BelongsToRelationPredicate;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Form\Factory\ChoicesBuilderFactory;
use HeimrichHannot\FlareBundle\Tests\Filter\BuildsFilterContextsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

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
            'whitelistParents' => \serialize(['3', '5', '5', '0']),
            'groupWhitelistParents' => \serialize([
                ['tablePtable' => 'tl_news_archive', 'whitelistParents' => \serialize(['1']), 'formatLabel' => ''],
                ['tablePtable' => 'tl_calendar', 'whitelistParents' => ['7'], 'formatLabel' => 'custom',
                    'formatLabelCustom' => '%title%'],
                ['tablePtable' => 'tl_empty', 'whitelistParents' => ''],
            ]),
            'useWhitelistForOptionsOnly' => '1',
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
            'whitelistParents' => \serialize(['2']),
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
    ): FilterContext {
        return $this->buildFilterContext(
            $this->createElement(),
            ['whitelist_parents' => $whitelist, 'use_whitelist_for_options_only' => $optionsOnly],
            $selected ? new ChoiceValue($selected) : null,
            null,
            'tl_news',
        );
    }

    /**
     * @param list<ContaoModelChoice> $selected
     */
    private function buildDynamic(bool $optionsOnly, array $selected = []): FilterContext
    {
        return $this->buildFilterContext(
            $this->createElement(),
            ['group_whitelist_parents' => self::GROUPS, 'use_whitelist_for_options_only' => $optionsOnly],
            $selected ? new ChoiceValue($selected) : null,
            null,
            'tl_content',
        );
    }

    private function createElement(): ArchiveFilterElement
    {
        $element = new ArchiveFilterElement();
        $element->setChoicesBuilderFactory(new ChoicesBuilderFactory(
            $this->createMock(TranslatorInterface::class),
            $this->createMock(ParameterBagInterface::class),
        ));

        return $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(array $row): array
    {
        $element = $this->createElement();

        $transformers = new TransformerResolver();
        $element->configureTransformers($transformers);

        $transformer = $transformers->resolve($model = new FilterModelStub($row));
        self::assertNotNull($transformer);

        $transformer($config = new ConfigBuilder(), $model);

        return $config->all();
    }
}
