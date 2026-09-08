<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter;

use HeimrichHannot\FlareBundle\Enum\SqlEquationOperator;
use HeimrichHannot\FlareBundle\Filter\Filter;
use HeimrichHannot\FlareBundle\Filter\FilterData;
use HeimrichHannot\FlareBundle\List\Driver\ListDriverInterface;
use HeimrichHannot\FlareBundle\List\ListSpec;
use HeimrichHannot\FlareBundle\Tests\List\StubFilterElement;
use PHPUnit\Framework\TestCase;

/**
 * THROWAWAY PROBE — delete once SPEC_FILTER_FORMS.md §14.1 is resolved.
 *
 * §9 asserts from language semantics that a `final readonly` value object of scalars, arrays,
 * enums and nested value objects "needs no hashing interface at all — it drops into the existing
 * fingerprint". §14.1 asks for that to be measured against {@see ListSpec::hash()} before the
 * decision is locked in. This probe measures it.
 *
 * The probe reaches the real hashing path with no new production code: `Filter::$data` is a
 * `?FilterData`, {@see FilterData::single()} accepts `mixed`, {@see FilterData::toArray()} returns
 * the value verbatim, and {@see Filter::fingerprint()} feeds it into the array
 * {@see ListSpec::hash()} serializes.
 *
 * What is at stake: `ListSpec::hash()`'s only consumer is an in-request memoization array
 * ({@see \HeimrichHannot\FlareBundle\Filter\Element\ArchiveFilterElement}), so an unstable hash
 * costs a cache miss, while a *colliding* hash would be a correctness bug. Every test below
 * asserts current behaviour, so the suite stays green; the docblocks say what each result means
 * for the §9 decision.
 *
 * @group probe
 */
final class ValueObjectSerializeProbeTest extends TestCase
{
    /**
     * Baseline: the claim §9 rests on. Two separately constructed, equal value objects hash the
     * same, because `serialize()` records class name and property values, never object identity.
     */
    public function testEqualButDistinctValueObjectsHashTheSame(): void
    {
        $a = $this->hashOf(new ProbeScalarValue('news', [1, 2, 3], true));
        $b = $this->hashOf(new ProbeScalarValue('news', [1, 2, 3], true));

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $this->hashOf(new ProbeScalarValue('news', [1, 2, 4], true)));
    }

    public function testHashSurvivesASerializeRoundTrip(): void
    {
        $value = new ProbeScalarValue('news', [1, 2, 3], true);
        $restored = \unserialize(\serialize($value));

        $this->assertSame($this->hashOf($value), $this->hashOf($restored));
    }

    /**
     * The finding §9 does not anticipate: `serialize()` is not a pure value function over an
     * object *graph*. A repeated object is emitted as a back-reference (`r:N;`), so a hash over
     * two filters differs depending on whether they share one value instance or hold two equal
     * ones. Today's code is immune because `Filter::fingerprint()` flattens through
     * `FilterData::toArray()`; §8's plan to move the hashing role onto the value object removes
     * that flattening. Conclusion for step 1: either keep a flattening step (§9's opt-in
     * `fingerprint(): array`) or accept the cache miss.
     */
    public function testHashDependsOnValueObjectInstanceSharing(): void
    {
        $shared = new ProbeScalarValue('news', [1], false);

        $sharedHash = $this->hashOfMany(['a' => $shared, 'b' => $shared]);
        $distinctHash = $this->hashOfMany([
            'a' => new ProbeScalarValue('news', [1], false),
            'b' => new ProbeScalarValue('news', [1], false),
        ]);

        $this->assertNotSame($sharedHash, $distinctHash);

        // The mechanism, not just the symptom.
        $this->assertStringContainsString('r:', \serialize([$shared, $shared]));
        $this->assertStringNotContainsString(
            'r:',
            \serialize([new ProbeScalarValue('news', [1], false), new ProbeScalarValue('news', [1], false)]),
        );
    }

    /**
     * Why §9 bans `\DateTimeInterface`: the same instant hashes differently depending on how its
     * timezone is expressed, because `DateTime*` serializes `date`, `timezone_type` (1 vs. 3) and
     * `timezone`. `DateRangeFilterElement`'s from/to is the live site.
     */
    public function testDateTimeTimezoneRepresentationChangesTheHash(): void
    {
        $offset = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('+01:00'));
        $named = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('Europe/Berlin'));

        $this->assertSame(
            $offset->getTimestamp(),
            $named->getTimestamp(),
            'Precondition: the two instances describe the same instant',
        );
        $this->assertNotSame($this->hashOf($offset), $this->hashOf($named));

        // Control: the same timezone identifier is stable.
        $this->assertSame(
            $this->hashOf(new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('Europe/Berlin'))),
            $this->hashOf($named),
        );
    }

    /**
     * Why §9 requires normalising in the constructor: arrays serialize in insertion order, for
     * both list and string keys. Note this hazard is already live for `Filter::$config` and
     * `ListSpec::$config`, which `hash()` serializes directly.
     */
    public function testArrayOrderChangesTheHash(): void
    {
        $this->assertNotSame($this->hashOf(['a', 'b']), $this->hashOf(['b', 'a']));
        $this->assertNotSame($this->hashOf(['a' => 1, 'b' => 2]), $this->hashOf(['b' => 2, 'a' => 1]));
    }

    /**
     * Why §9 requires storing ids rather than Contao models: a model carries mutation state
     * alongside its row, so an unrelated change to that state moves the hash. `ProbeModelLike`
     * stands in for `Model::$arrData` / `Model::$arrModified` without needing a database.
     */
    public function testModelLikeValueDragsMutationStateIntoTheHash(): void
    {
        $pristine = new ProbeModelLike(['id' => 7, 'title' => 'News'], []);
        $touched = new ProbeModelLike(['id' => 7, 'title' => 'News'], ['title' => true]);

        $this->assertSame($pristine->row, $touched->row, 'Precondition: the logical row is identical');
        $this->assertNotSame($this->hashOf($pristine), $this->hashOf($touched));

        // Storing the id instead, as §9 requires, is stable.
        $this->assertSame($this->hashOf(['id' => 7]), $this->hashOf(['id' => 7]));
    }

    /**
     * Why §9 bans closures. This also surfaces a pre-existing hazard: `Filter::$data` already
     * accepts a closure through `mixed`, and any consumer calling `ListSpec::hash()` would throw.
     */
    public function testClosureInAValueMakesHashingThrow(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Serialization of 'Closure' is not allowed");

        $this->hashOf(static fn (): null => null);
    }

    /**
     * The positive control for the containment rule: enums and nested value objects are value
     * stable. Enum cases are singletons, so the instance-sharing caveat above does not apply.
     */
    public function testEnumsAndNestedValueObjectsAreValueStable(): void
    {
        $make = static fn (): ProbeNestedValue => new ProbeNestedValue(
            SqlEquationOperator::EQUALS,
            new ProbeScalarValue('news', [1], true),
        );

        $this->assertSame($this->hashOf($make()), $this->hashOf($make()));
        $this->assertNotSame(
            $this->hashOf($make()),
            $this->hashOf(new ProbeNestedValue(
                SqlEquationOperator::NOT_EQUALS,
                new ProbeScalarValue('news', [1], true),
            )),
        );
    }

    private function hashOf(mixed $value): string
    {
        return $this->hashOfMany(['probe' => $value]);
    }

    /**
     * @param array<string, mixed> $values One filter per entry, each carrying the value as its
     *   programmatic runtime data.
     */
    private function hashOfMany(array $values): string
    {
        $driver = new class implements ListDriverInterface {
            public function resolveDcTable(string $type, array $config, array $attributes): string
            {
                return 'tl_test';
            }
        };

        $list = new ListSpec(driver: $driver, type: 'test_list', dc: 'tl_test');

        foreach ($values as $alias => $value)
        {
            $list = $list->withFilter(new Filter(
                element: new StubFilterElement(),
                type: 'test_element',
                data: FilterData::single($value),
                alias: $alias,
            ));
        }

        return $list->hash();
    }
}

/**
 * Named, not anonymous: an anonymous class name embeds a null byte plus the defining file and
 * line, which `serialize()` would include and which would make every assertion here
 * file-position-dependent.
 */
final readonly class ProbeScalarValue
{
    /**
     * @param list<int> $ids
     */
    public function __construct(
        public string $table,
        public array  $ids,
        public bool   $inverted,
    ) {}
}

final readonly class ProbeNestedValue
{
    public function __construct(
        public SqlEquationOperator $operator,
        public ProbeScalarValue    $value,
    ) {}
}

/**
 * Stands in for a Contao model: a row plus mutation state, as `Model::$arrData` / `$arrModified`.
 */
final readonly class ProbeModelLike
{
    /**
     * @param array<string, mixed> $row
     * @param array<string, bool> $modified
     */
    public function __construct(
        public array $row,
        public array $modified,
    ) {}
}
