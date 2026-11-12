<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\List\Entry;

use HeimrichHannot\FlareBundle\List\Entry\EntryCollection;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class EntryTwigTest extends TestCase
{
    private function render(string $template, array $context, bool $strict = true): string
    {
        $twig = new Environment(new ArrayLoader(['t' => $template]), ['strict_variables' => $strict]);
        return $twig->render('t', $context);
    }

    public function testCollectionWorksWithEmptyLengthAndLoopLast(): void
    {
        $entries = new EntryCollection([['id' => 1], ['id' => 2]]);
        self::assertSame('2', $this->render('{% if e is empty %}x{% endif %}{{ e|length }}', ['e' => $entries]));
        self::assertSame('1,2', $this->render(
            '{% for x in e %}{{ x.row.id }}{% if not loop.last %},{% endif %}{% endfor %}',
            ['e' => $entries],
        ));
        self::assertSame('1212', $this->render(
            '{% for x in e %}{{ x.id }}{% endfor %}{% for x in e %}{{ x.id }}{% endfor %}',
            ['e' => $entries],
        ));
        self::assertSame(
            'empty',
            $this->render('{% if e is empty %}empty{% endif %}', ['e' => new EntryCollection()]),
        );
    }

    public function testToAndRowTo(): void
    {
        $entry = (new EntryCollection([['id' => 1, 'to' => 'column']], static fn (): string => '/reader/1'))->first();
        self::assertSame('/reader/1|column', $this->render('{{ entry.to }}|{{ entry.row.to }}', ['entry' => $entry]));
    }

    public function testNullColumnUnderStrictVariables(): void
    {
        $entry = (new EntryCollection([['id' => 1, 'subtitle' => null]]))->first();
        self::assertSame('null', $this->render('{{ entry.subtitle is null ? "null" : "set" }}', ['entry' => $entry]));
    }

    public function testUnknownNameFollowsTwigsRules(): void
    {
        $entry = (new EntryCollection([['id' => 1]]))->first();
        self::assertSame('', $this->render('{{ entry.missing }}', ['entry' => $entry], strict: false));
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->render('{{ entry.missing }}', ['entry' => $entry]);
    }
}
