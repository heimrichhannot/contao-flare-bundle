<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Filter\Value\Choice;

use HeimrichHannot\FlareBundle\Filter\Value\Choice\ChoiceKey;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ContaoModelChoice;
use HeimrichHannot\FlareBundle\Filter\Value\Choice\ValueChoice;
use PHPUnit\Framework\TestCase;

final class ChoiceObjectsTest extends TestCase
{
    public function testModelReferenceHasCanonicalTableKey(): void
    {
        $ref = ContaoModelChoice::ref('tl_news_archive', 5);
        self::assertSame('tl_news_archive', $ref->getChoiceScope());
        self::assertSame('5', $ref->getChoiceLocalKey());
        self::assertSame('tl_news_archive.5', ChoiceKey::of($ref));
        self::assertNull($ref->getModel());
        self::assertSame([], $ref->getLabelParameters());
    }

    public function testValueChoiceWithADotIsUnscoped(): void
    {
        $choice = new ValueChoice('1.5', 'One and a half');
        self::assertNull($choice->getChoiceScope());
        self::assertSame('1.5', ChoiceKey::of($choice));
        self::assertSame(['%value%' => '1.5'], $choice->getLabelParameters());
    }
}
