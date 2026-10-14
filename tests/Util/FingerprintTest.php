<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Util;

use HeimrichHannot\FlareBundle\Enum\BoolBinaryChoices;
use HeimrichHannot\FlareBundle\Filter\Value\BoolValue;
use HeimrichHannot\FlareBundle\Filter\Value\ChoiceValue;
use HeimrichHannot\FlareBundle\Filter\Value\KeywordsValue;
use HeimrichHannot\FlareBundle\Util\Fingerprint;
use PHPUnit\Framework\TestCase;

/**
 * Closes SPEC_FILTER_FORMS.md §14.1.
 *
 * tests/Filter/ValueObjectSerializeProbeTest.php measured that `serialize()` is not a pure value
 * function over an object *graph*: a repeated object is emitted as a back-reference (`r:N;`), so a
 * hash over two filters differs depending on whether they share one value instance or hold two
 * equal ones. That probe left step 1 a choice between keeping a flattening step and accepting the
 * cache miss. This suite is the flattening step, and asserts the asymmetry is gone.
 *
 * Each test says what its result means for the decision, not merely what it asserts.
 */
final class FingerprintTest extends TestCase
{
    /**
     * The finding §14.1 opened, closed. Flattening removes every object from the structure, so the
     * back-reference cannot be emitted at all — which is why this is structural rather than a
     * property each value object has to remember to uphold.
     */
    public function testFlatteningRemovesTheInstanceSharingAsymmetry(): void
    {
        $shared = new BoolValue(true);

        $sharedHash = self::hashOf(['a' => $shared, 'b' => $shared]);
        $distinctHash = self::hashOf(['a' => new BoolValue(true), 'b' => new BoolValue(true)]);

        self::assertSame($sharedHash, $distinctHash);
        self::assertStringNotContainsString('r:', \serialize(Fingerprint::flatten([$shared, $shared])));

        // The mechanism the probe recorded, still present without flattening.
        self::assertStringContainsString('r:', \serialize([$shared, $shared]));
    }

    /** Positive control: the flattener is still injective over unequal values. */
    public function testUnequalValuesStillHashDifferently(): void
    {
        self::assertNotSame(self::hashOf(new BoolValue(true)), self::hashOf(new BoolValue(false)));
        self::assertNotSame(self::hashOf(new ChoiceValue(['a'])), self::hashOf(new ChoiceValue(['b'])));

        // Class identity is carried, so two structurally identical value objects do not collide.
        self::assertNotSame(self::hashOf(new ChoiceValue(['a'])), self::hashOf(new KeywordsValue('a')));
    }

    public function testRoundTripsThroughSerializeUnchanged(): void
    {
        $flat = Fingerprint::flatten(new ChoiceValue(['b', 'a']));

        self::assertSame($flat, \unserialize(\serialize($flat)));
    }

    /**
     * Probe finding #7: `Filter::$data` and `$config` accept a closure through `mixed`, and hashing
     * then *threw*. Trading that crash for a documented same-line collision is the deliberate call
     * — a collision here costs a cache miss, an exception costs the request.
     */
    public function testClosuresNoLongerMakeHashingThrow(): void
    {
        $closure = static fn (): null => null;

        self::assertIsString(self::hashOf($closure));
        self::assertSame(self::hashOf($closure), self::hashOf($closure));
        self::assertNotSame(self::hashOf($closure), self::hashOf(static fn (): int => 1));
    }

    /**
     * Probe findings #4 and #6, restated as the flattener's contract rather than a prohibition: a
     * `\DateTimeInterface` or a Contao model still hashes badly, but it degrades to a marked array
     * instead of corrupting the structure. Keeping them out is the containment rule's job, enforced
     * by ValueObjectContainmentTest.
     */
    public function testNonConformingValuesDegradeInsteadOfCorrupting(): void
    {
        $flat = Fingerprint::flatten(new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC')));

        self::assertIsArray($flat);
        self::assertSame(\DateTimeImmutable::class, $flat['*class*']);
    }

    /** Enums are value stable and must carry their class, or two enums sharing a case name collide. */
    public function testEnumsFlattenToClassAndBackingValue(): void
    {
        self::assertSame(
            [BoolBinaryChoices::class, 'null_true'],
            Fingerprint::flatten(BoolBinaryChoices::NULL_TRUE),
        );
    }

    public function testScalarsAndNullPassThroughUntouched(): void
    {
        self::assertNull(Fingerprint::flatten(null));
        self::assertTrue(Fingerprint::flatten(true));
        self::assertSame(42, Fingerprint::flatten(42));
        self::assertSame('x', Fingerprint::flatten('x'));
        self::assertSame(1.5, Fingerprint::flatten(1.5));
    }

    /** NAN !== NAN and -0.0 == 0.0; both would otherwise make the hash non-reflexive. */
    public function testNonReflexiveFloatsAreNormalised(): void
    {
        self::assertSame(self::hashOf(-0.0), self::hashOf(0.0));
        self::assertSame(self::hashOf(\NAN), self::hashOf(\NAN));
    }

    public function testArrayKeysAndNestingArePreserved(): void
    {
        self::assertSame(
            ['a' => ['*class*' => BoolValue::class, 'state' => true], 7 => 'x'],
            Fingerprint::flatten(['a' => new BoolValue(true), 7 => 'x']),
        );
    }

    public function testDepthIsBounded(): void
    {
        $deep = [];
        $cursor = &$deep;

        for ($i = 0; $i < 20; ++$i)
        {
            $cursor['next'] = [];
            $cursor = &$cursor['next'];
        }

        unset($cursor);

        // Returns rather than blowing the stack, and the marker is reachable.
        self::assertIsArray(Fingerprint::flatten($deep));
        self::assertSame(['*depth-exceeded*', 'array'], Fingerprint::flatten([[1]], 1)[0]);
    }

    private static function hashOf(mixed $value): string
    {
        return \sha1(\serialize(Fingerprint::flatten($value)));
    }
}
