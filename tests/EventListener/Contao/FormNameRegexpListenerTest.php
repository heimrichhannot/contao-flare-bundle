<?php

declare(strict_types=1);

namespace HeimrichHannot\FlareBundle\Tests\EventListener\Contao;

use Contao\Widget;
use HeimrichHannot\FlareBundle\EventListener\Contao\FormNameRegexpListener;
use PHPUnit\Framework\TestCase;

final class FormNameRegexpListenerTest extends TestCase
{
    /** @var list<string> */
    private array $errors = [];

    protected function setUp(): void
    {
        $GLOBALS['TL_LANG']['ERR']['flare']['form_name'] = 'Not a form name.';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TL_LANG']);
    }

    private function widget(): Widget
    {
        $widget = $this->createMock(Widget::class);
        $widget->method('addError')->willReturnCallback(function (string $error): void {
            $this->errors[] = $error;
        });

        return $widget;
    }

    public function testOtherRegexpsAreNotHandled(): void
    {
        self::assertFalse((new FormNameRegexpListener())('alias', 'price.min', $this->widget()));
        self::assertSame([], $this->errors);
    }

    /**
     * @dataProvider validFormNames
     */
    public function testValidFormNamesPass(string $input): void
    {
        self::assertTrue((new FormNameRegexpListener())(FormNameRegexpListener::RGXP, $input, $this->widget()));
        self::assertSame([], $this->errors);
    }

    public static function validFormNames(): iterable
    {
        yield 'underscore' => ['price_min'];
        yield 'numeric' => ['12'];
        yield 'zero' => ['0'];
        yield 'hyphen inside' => ['a-b'];
        yield 'colon inside' => ['a:b'];
    }

    /**
     * @dataProvider invalidFormNames
     */
    public function testInvalidFormNamesAreRejected(string $input): void
    {
        self::assertTrue((new FormNameRegexpListener())(FormNameRegexpListener::RGXP, $input, $this->widget()));
        self::assertSame(['Not a form name.'], $this->errors);
    }

    public static function invalidFormNames(): iterable
    {
        yield 'dot' => ['price.min'];
        yield 'leading hyphen' => ['-sort'];
        yield 'non-ASCII letters' => ['größe'];
        yield 'space' => ['a b'];
    }
}
