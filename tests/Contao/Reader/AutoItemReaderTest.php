<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\Contao\Reader;

use Contao\Input;
use Contao\TestCase\ContaoTestCase;
use HeimrichHannot\FlareBundle\Contao\Reader\AutoItemReader;

final class AutoItemReaderTest extends ContaoTestCase
{
    public function testReadsTheAutoItemWithoutMarkingItUsed(): void
    {
        $input = $this->mockAdapter(['get']);
        $input->expects(self::once())->method('get')->with('auto_item', false, true)->willReturn('a&#35;b');

        self::assertSame('a&#35;b', (new AutoItemReader($this->mockContaoFramework([Input::class => $input])))->read());
    }

    /**
     * @dataProvider provideMissing
     */
    public function testMissingAutoItemIsNull(mixed $value): void
    {
        $input = $this->mockConfiguredAdapter(['get' => $value]);

        self::assertNull((new AutoItemReader($this->mockContaoFramework([Input::class => $input])))->read());
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function provideMissing(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'array' => [['a']];
    }
}
